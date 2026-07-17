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
        $matched = 0; $dropped = 0;
        foreach ( (array) $rows as $row ) {
            $d = self::diff_row( $type, $row, $opts );
            $out['rows'][] = $d;
            $out['summary'][ $d['status'] ]++;
            if ( ! empty( $d['alerts'] ) ) $out['summary']['alerts']++;
            if ( $type === 'precios_catalogo' && $d['product'] && isset( $d['fields']['precio'] ) ) {
                $f = $d['fields']['precio'];
                if ( $f['incoming'] !== '' && (int) $f['current'] > 0 ) {
                    $matched++;
                    if ( (int) $f['incoming'] < (int) $f['current'] ) $dropped++;
                }
            }
        }
        if ( $type === 'precios_catalogo' && $matched > 0 && ( $dropped / $matched ) > self::MOSTLY_DROP_RATIO ) {
            $out['global_alerts'][] = 'mostly_price_drop';
        }
        return $out;
    }

    private static function diff_row( $type, $row, $opts ) {
        $line = $row['__line'] ?? '?';
        $pid  = (int) ( $row['id'] ?? 0 );
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
        return [
            '__line'=>$line, 'status'=>$status,
            'product'=>[ 'id'=>$pid, 'name'=>$product ? $product->get_name() : $name ],
            'fields'=>$fields, 'alerts'=>self::row_alerts( $fields ), 'candidates'=>[],
        ];
    }

    private static function fields_for( $type, $product, $pid, $row, $opts ) {
        $in_price = (int) preg_replace( '/[^0-9]/', '', (string) ( $row['precio normal'] ?? ( $row['precio'] ?? '' ) ) );
        $cur_price = (int) get_post_meta( $pid, '_glo_price', true );
        $fields = [];
        // precio (escrito en publico)
        $fields['precio'] = self::num_field( (string) $cur_price, $in_price > 0 ? (string) $in_price : '', true );
        // presentacion / empaque (escritos si vienen)
        $fields['presentacion'] = self::text_field(
            (string) get_post_meta( $pid, '_glo_presentacion_texto', true ),
            trim( (string) ( $row['presentacion'] ?? '' ) ), true );
        $fields['empaque'] = self::text_field(
            (string) get_post_meta( $pid, '_glo_empaque_texto', true ),
            trim( (string) ( $row['empaque'] ?? '' ) ), true );
        // disponibilidad: escrita solo si sync_stock
        $sync = ! empty( $opts['sync_stock'] );
        $cur_disp = ( $product->get_stock_status() === 'outofstock' ) ? 'AGOTADO' : 'DISPONIBLE';
        $in_disp_raw = trim( (string) ( $row['disponibilidad'] ?? ( $row['inventario'] ?? '' ) ) );
        $in_disp = $in_disp_raw === '' ? '' : ( stripos( $in_disp_raw, 'agot' ) !== false ? 'AGOTADO' : 'DISPONIBLE' );
        $fields['disponibilidad'] = $sync
            ? self::text_field( $cur_disp, $in_disp, true )
            : self::ref_field( $cur_disp, $in_disp );
        // peso: siempre referencia (no se escribe por ID)
        $in_weight = trim( (string) ( $row['peso (kg)'] ?? '' ) );
        $fields['peso'] = self::ref_field( (string) $product->get_weight(), $in_weight );
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
}
