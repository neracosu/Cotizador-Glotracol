<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Motor de cotejo de importación: compara cada fila parseada contra los valores
 * actuales del producto y clasifica fila (change/new/same/unmatched) y campo
 * (change/same/fill/skip/ref). No escribe nada ni imprime UI.
 */
class Glotracol_Quote_Import_Diff {

    const MOSTLY_DROP_RATIO = 0.6;
    const BIG_SWING = 0.4;

    public static function build( $type, $rows, $opts = [] ) {
        $out = [ 'rows'=>[], 'summary'=>[ 'change'=>0,'new'=>0,'same'=>0,'unmatched'=>0,'alerts'=>0 ], 'global_alerts'=>[] ];
        $price_types = [ 'precios_catalogo', 'precios_publicos' ];
        $matched = 0; $dropped = 0;
        foreach ( (array) $rows as $row ) {
            $d = self::diff_row( $type, $row, $opts );
            $out['rows'][] = $d;
            $out['summary'][ $d['status'] ]++;
            if ( ! empty( $d['alerts'] ) ) $out['summary']['alerts']++;
            if ( in_array( $type, $price_types, true ) && $d['product'] && isset( $d['fields']['precio'] ) ) {
                $f = $d['fields']['precio'];
                if ( $f['incoming'] !== '' && (int) $f['current'] > 0 ) {
                    $matched++;
                    if ( (int) $f['incoming'] < (int) $f['current'] ) $dropped++;
                }
            }
        }
        if ( in_array( $type, $price_types, true ) && $matched > 0 && ( $dropped / $matched ) > self::MOSTLY_DROP_RATIO ) {
            $out['global_alerts'][] = 'mostly_price_drop';
        }

        // Depurador: detectar corrimiento de fila y referencias duplicadas (imports de precio por ID).
        if ( in_array( $type, $price_types, true ) ) {
            $seq = [];   // secuencia ordenada de filas resueltas, para el detector.
            $refs = [];  // ids resueltos, para duplicados.
            foreach ( $out['rows'] as $ri => $d ) {
                if ( ! $d['product'] || empty( $d['product']['id'] ) || ! isset( $d['fields']['precio'] ) ) continue;
                $refs[] = (string) $d['product']['id'];
                $seq[] = [
                    'row'      => $ri,
                    'line'     => $d['__line'],
                    'id'       => (int) $d['product']['id'],
                    'name'     => $d['product']['name'],
                    'incoming' => (int) $d['fields']['precio']['incoming'],
                    'current'  => (int) $d['fields']['precio']['current'],
                ];
            }
            $shift = self::detect_shift( $seq, 4 );
            if ( $shift ) {
                $realign = self::suggest_realign( $seq, $shift );
                foreach ( $realign as $k => &$ra ) {
                    $ra['line'] = $seq[ $k ]['line'] ?? '';
                    $ra['name'] = $seq[ $k ]['name'] ?? '';
                    $ra['from'] = $seq[ $k ]['incoming'] ?? '';   // precio tal como vino en el archivo.
                }
                unset( $ra );
                $out['global_alerts'][] = [
                    'type' => 'shift',
                    'dir'  => $shift['dir'],
                    'len'  => $shift['len'],
                    'from_line' => $seq[ $shift['from'] ]['line'] ?? '?',
                    'to_line'   => $seq[ $shift['to'] ]['line'] ?? '?',
                    'realign'   => $realign,
                ];
            }
            $dups = self::detect_duplicates( $refs );
            if ( $dups ) {
                $out['global_alerts'][] = [ 'type' => 'duplicates', 'refs' => array_keys( $dups ) ];
            }
        }
        return $out;
    }

    private static function diff_row( $type, $row, $opts ) {
        $line = $row['__line'] ?? '?';
        $pid  = (int) ( $row['id'] ?? 0 );
        // Lista A "rápida" (precios_publicos): la referencia viene en `sku` (ID o SKU real).
        if ( $pid <= 0 && isset( $row['sku'] ) && trim( (string) $row['sku'] ) !== '' ) {
            $ref = trim( (string) $row['sku'] );
            $pid = (int) Glotracol_Quote_Importer::resolve_product_id_by_ref( $ref );
            if ( $pid <= 0 ) return self::mk_unmatched( $line, $ref );
        }
        $name = trim( (string) ( $row['nombre'] ?? '' ) );
        $create_missing = ! empty( $opts['create_missing'] );

        $product = null;
        if ( $pid > 0 ) {
            $product = function_exists('wc_get_product') ? wc_get_product( $pid ) : null;
            if ( ! $product ) return self::mk_unmatched( $line, $name );
        } else {
            $match = $name !== '' ? (int) Glotracol_Quote_Import_Reader::find_by_name( $name, 'product' ) : 0;
            if ( $match > 0 ) { $product = wc_get_product( $match ); $pid = $match; }
            elseif ( $create_missing && $name !== '' ) return self::mk_new( $line, $name, $type, $row, $opts );
            else return self::mk_unmatched( $line, $name );
        }

        $fields = self::fields_for( $type, $product, $pid, $row, $opts );
        $status = 'same';
        foreach ( $fields as $f ) {
            if ( in_array( $f['state'], [ 'change', 'fill' ], true ) ) { $status = 'change'; break; }
        }
        $alerts = self::row_alerts( $fields );
        // Depurador: si el archivo trae nombre y no coincide con el del catálogo, avisar
        // (posible ID equivocado o archivo desalineado).
        if ( $name !== '' && $product && self::norm( $product->get_name() ) !== self::norm( $name ) ) {
            $alerts[] = 'name_mismatch';
        }
        return [
            '__line'=>$line, 'status'=>$status,
            'product'=>[ 'id'=>$pid, 'name'=>$product ? $product->get_name() : $name ],
            'fields'=>$fields, 'alerts'=>$alerts, 'candidates'=>[],
        ];
    }

    private static function fields_for( $type, $product, $pid, $row, $opts ) {
        $in_price = (int) preg_replace( '/[^0-9]/', '', (string) ( $row['precio normal'] ?? ( $row['precio'] ?? '' ) ) );
        $in_price_s = $in_price > 0 ? (string) $in_price : '';
        $fields = [];

        if ( $type === 'precios_publicos' ) {
            // Lista A rápida: sólo el precio público (_glo_price).
            $fields['precio'] = self::num_field( (string) (int) get_post_meta( $pid, '_glo_price', true ), $in_price_s, true );
            return $fields;
        }

        if ( $type === 'precios_lista_b' ) {
            $cur_b = (int) get_post_meta( $pid, '_glo_price_b', true );
            $fields['precio']  = self::num_field( (string) $cur_b, $in_price_s, true );
            $fields['lista_a'] = self::ref_field( (string) (int) get_post_meta( $pid, '_glo_price', true ), '' );
            return $fields;
        }

        if ( $type === 'precios_catalogo' && ( $opts['mode'] ?? 'publico' ) === 'b2b' ) {
            $cp = get_post_meta( (int) ( $opts['client_id'] ?? 0 ), '_glo_client_pricing', true );
            $cur = is_array( $cp ) ? (int) ( $cp[ $pid ] ?? 0 ) : 0;
            $fields['precio'] = self::num_field( (string) $cur, $in_price_s, true );
            return $fields;
        }

        // catálogo publico
        $fields['precio'] = self::num_field( (string) (int) get_post_meta( $pid, '_glo_price', true ), $in_price_s, true );
        $fields['presentacion'] = self::text_field( (string) get_post_meta( $pid, '_glo_presentacion_texto', true ), trim( (string) ( $row['presentacion'] ?? '' ) ), true );
        $fields['empaque'] = self::text_field( (string) get_post_meta( $pid, '_glo_empaque_texto', true ), trim( (string) ( $row['empaque'] ?? '' ) ), true );
        $sync = ! empty( $opts['sync_stock'] );
        $cur_disp = ( $product->get_stock_status() === 'outofstock' ) ? 'AGOTADO' : 'DISPONIBLE';
        $in_disp_raw = trim( (string) ( $row['disponibilidad'] ?? ( $row['inventario'] ?? '' ) ) );
        $in_disp = $in_disp_raw === '' ? '' : ( stripos( $in_disp_raw, 'agot' ) !== false ? 'AGOTADO' : 'DISPONIBLE' );
        $fields['disponibilidad'] = $sync ? self::text_field( $cur_disp, $in_disp, true ) : self::ref_field( $cur_disp, $in_disp );
        $fields['peso'] = self::ref_field( (string) $product->get_weight(), trim( (string) ( $row['peso (kg)'] ?? '' ) ) );
        return $fields;
    }

    private static function num_field( $current, $incoming, $editable ) {
        if ( $incoming === '' ) $state = 'skip';
        elseif ( (int) $current <= 0 ) $state = 'fill';
        elseif ( (int) $current === (int) $incoming ) $state = 'same';
        else $state = 'change';
        return [ 'current'=>$current, 'incoming'=>$incoming, 'state'=>$state, 'editable'=>$editable, 'written'=>true ];
    }

    private static function text_field( $current, $incoming, $editable ) {
        if ( $incoming === '' ) $state = 'skip';
        elseif ( $current === '' ) $state = 'fill';
        elseif ( self::norm( $current ) === self::norm( $incoming ) ) $state = 'same';
        else $state = 'change';
        return [ 'current'=>$current, 'incoming'=>$incoming, 'state'=>$state, 'editable'=>$editable, 'written'=>true ];
    }

    private static function ref_field( $current, $incoming ) {
        return [ 'current'=>$current, 'incoming'=>$incoming, 'state'=>'ref', 'editable'=>false, 'written'=>false ];
    }

    private static function row_alerts( $fields ) {
        $a = [];
        if ( isset( $fields['precio'] ) && $fields['precio']['written'] ) {
            $cur = (int) $fields['precio']['current'];
            $in  = $fields['precio']['incoming'];
            if ( $in !== '' && (int) $in <= 0 ) $a[] = 'to_zero';
            elseif ( $in !== '' && $cur > 0 && abs( (int) $in - $cur ) / $cur > self::BIG_SWING ) $a[] = 'big_swing';
        }
        return $a;
    }

    private static function norm( $s ) {
        $s = (string) $s;
        if ( function_exists( 'remove_accents' ) ) $s = remove_accents( $s );
        return strtoupper( trim( preg_replace( '/\s+/u', ' ', $s ) ) );
    }

    private static function mk_unmatched( $line, $name ) {
        return [ '__line'=>$line, 'status'=>'unmatched', 'product'=>null, 'fields'=>[], 'alerts'=>[],
                 'candidates'=> $name !== '' ? Glotracol_Quote_Import_Reader::suggest_candidates( $name, 'product', 3 ) : [] ];
    }

    private static function mk_new( $line, $name, $type, $row, $opts ) {
        $fields = [ 'precio'=>self::num_field( '0', (string) (int) preg_replace('/[^0-9]/','',(string)($row['precio normal'] ?? ($row['precio'] ?? ''))), true ) ];
        return [ '__line'=>$line, 'status'=>'new', 'product'=>[ 'id'=>0, 'name'=>$name ], 'fields'=>$fields, 'alerts'=>[], 'candidates'=>[] ];
    }

    /* -------------------------------------------------------------------------
     * DEPURADOR: detector de corrimiento, duplicados y realineado.
     * ---------------------------------------------------------------------- */

    /**
     * Detecta un corrimiento constante de ±1 fila en una secuencia ordenada.
     *
     * Cada elemento: [ 'id'=>int, 'incoming'=>int, 'current'=>int ]. El síntoma de un
     * archivo corrido es que el precio entrante de una fila coincide con el precio ACTUAL
     * del producto vecino (i+dir): la celda vacía de una fila empujó todos los precios.
     *
     * @param array $seq
     * @param int   $min_run  corrida mínima consecutiva para declarar corrimiento.
     * @return array|null  [ 'dir'=>+1|-1, 'from'=>idx, 'to'=>idx, 'len'=>n ] o null.
     */
    public static function detect_shift( $seq, $min_run = 4 ) {
        $seq = array_values( (array) $seq );
        $n = count( $seq );
        if ( $n < $min_run + 1 ) return null;
        foreach ( [ 1, -1 ] as $dir ) {
            $best = null; $start = null; $len = 0;
            for ( $i = 0; $i < $n; $i++ ) {
                $j = $i + $dir;
                $ok = isset( $seq[ $j ] )
                    && (int) $seq[ $i ]['incoming'] > 0
                    && (int) $seq[ $j ]['current'] > 0
                    && (int) $seq[ $i ]['incoming'] === (int) $seq[ $j ]['current'];
                if ( $ok ) {
                    if ( $start === null ) { $start = $i; $len = 1; } else { $len++; }
                } else {
                    if ( $len >= $min_run && ( $best === null || $len > $best['len'] ) ) {
                        $best = [ 'dir'=>$dir, 'from'=>$start, 'to'=>$i - 1, 'len'=>$len ];
                    }
                    $start = null; $len = 0;
                }
            }
            if ( $len >= $min_run && ( $best === null || $len > $best['len'] ) ) {
                $best = [ 'dir'=>$dir, 'from'=>$start, 'to'=>$n - 1, 'len'=>$len ];
            }
            if ( $best ) return $best;
        }
        return null;
    }

    /**
     * Propone el realineado ACOTADO al tramo corrido detectado (nunca toca la cabecera/cola
     * que ya está alineada). Dentro del tramo reasigna a cada fila el precio de su vecino:
     * la fila origen queda sin precio (su celda estaba vacía y empujó el resto).
     *
     * @param array $seq
     * @param array $shift  [ 'dir'=>±1, 'from'=>idx, 'to'=>idx ] devuelto por detect_shift().
     * @return array  lista [ 'id'=>int, 'incoming'=>int|'' ] en el mismo orden que $seq.
     */
    public static function suggest_realign( $seq, $shift ) {
        $seq  = array_values( (array) $seq );
        $n    = count( $seq );
        $dir  = (int) ( $shift['dir'] ?? 0 );
        $from = (int) ( $shift['from'] ?? 0 );
        $to   = (int) ( $shift['to'] ?? ( $n - 1 ) );
        $out  = [];
        for ( $i = 0; $i < $n; $i++ ) {
            $new = $seq[ $i ]['incoming']; // por defecto, sin cambio (fuera del tramo).
            if ( $dir === 1 && $i >= $from && $i <= $to + 1 ) {
                $src = $i - 1;
                $new = ( $src >= $from && $src <= $to ) ? $seq[ $src ]['incoming'] : '';
            } elseif ( $dir === -1 && $i >= $from - 1 && $i <= $to ) {
                $src = $i + 1;
                $new = ( $src >= $from && $src <= $to ) ? $seq[ $src ]['incoming'] : '';
            }
            $out[] = [ 'id' => $seq[ $i ]['id'], 'incoming' => $new ];
        }
        return $out;
    }

    /**
     * Devuelve las referencias que aparecen más de una vez → [ ref => [posiciones] ].
     */
    public static function detect_duplicates( $refs ) {
        $seen = [];
        foreach ( (array) $refs as $i => $ref ) {
            $ref = trim( (string) $ref );
            if ( $ref === '' ) continue;
            $seen[ $ref ][] = $i;
        }
        return array_filter( $seen, function ( $pos ) { return count( $pos ) > 1; } );
    }
}
