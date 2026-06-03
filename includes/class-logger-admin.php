<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pantalla "Logs" en el admin para ver eventos del plugin.
 */
class Glotracol_Quote_Logger_Admin {

	const PAGE_SLUG = 'glotracol-quote-logs';
	const NONCE_ACTION = 'gloq_logger';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_gloq_logs_clear', [ $this, 'handle_clear' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Logs',
			'Logs',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$level = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : '';
		$cat   = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '';
		$limit = isset( $_GET['limit'] ) ? max( 10, min( 500, (int) $_GET['limit'] ) ) : 100;

		$entries = Glotracol_Quote_Logger::get_entries( $limit, $level, $cat );
		$counts  = Glotracol_Quote_Logger::counts();
		$cats    = Glotracol_Quote_Logger::categories();
		$flash   = isset( $_GET['gloq_log_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['gloq_log_msg'] ) ) : '';
		?>
		<div class="wrap gloq-logs">
			<h1>Logs del cotizador</h1>
			<p>Eventos registrados por el plugin: importaciones, emails, webhooks, errores de compatibilidad, etc. Útil para diagnosticar problemas. Las entradas más recientes aparecen primero. Se conservan las últimas <?php echo (int) Glotracol_Quote_Logger::MAX_ENTRIES; ?> entradas (las anteriores se descartan automáticamente).</p>

			<?php if ( $flash === 'cleared' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Log vaciado.</p></div>
			<?php endif; ?>

			<div class="gloq-logs-stats">
				<span class="gloq-log-stat gloq-log-stat-total"><strong><?php echo (int) $counts['total']; ?></strong> total</span>
				<span class="gloq-log-stat gloq-log-stat-error"><strong><?php echo (int) $counts['error']; ?></strong> errors</span>
				<span class="gloq-log-stat gloq-log-stat-warn"><strong><?php echo (int) $counts['warn']; ?></strong> warnings</span>
				<span class="gloq-log-stat gloq-log-stat-info"><strong><?php echo (int) $counts['info']; ?></strong> info</span>
				<span class="gloq-log-stat gloq-log-stat-debug"><strong><?php echo (int) $counts['debug']; ?></strong> debug</span>
			</div>

			<form method="get" class="gloq-logs-filters">
				<input type="hidden" name="post_type" value="glo_quote">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<label>Nivel
					<select name="level">
						<option value="">Todos</option>
						<option value="error" <?php selected( $level, 'error' ); ?>>Error</option>
						<option value="warn"  <?php selected( $level, 'warn' );  ?>>Warning</option>
						<option value="info"  <?php selected( $level, 'info' );  ?>>Info</option>
						<option value="debug" <?php selected( $level, 'debug' ); ?>>Debug</option>
					</select>
				</label>
				<label>Categoría
					<select name="cat">
						<option value="">Todas</option>
						<?php foreach ( $cats as $c => $n ) : ?>
							<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cat, $c ); ?>><?php echo esc_html( $c ); ?> (<?php echo (int) $n; ?>)</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Mostrar
					<select name="limit">
						<?php foreach ( [ 50, 100, 200, 500 ] as $L ) : ?>
							<option value="<?php echo $L; ?>" <?php selected( $limit, $L ); ?>><?php echo $L; ?> entradas</option>
						<?php endforeach; ?>
					</select>
				</label>
				<input type="submit" class="button button-primary" value="Filtrar">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ); ?>" class="button">Limpiar filtros</a>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:10px" onsubmit="return confirm('¿Vaciar todo el log? Esta acción no se puede deshacer.');">
					<input type="hidden" name="action" value="gloq_logs_clear">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<input type="submit" class="button button-link-delete" value="Vaciar log">
				</form>
			</form>

			<?php if ( empty( $entries ) ) : ?>
				<div class="notice notice-info inline" style="margin:20px 0;padding:14px 18px"><p>No hay entradas en el log con los filtros aplicados.</p></div>
			<?php else : ?>
				<table class="wp-list-table widefat striped gloq-logs-table">
					<thead>
						<tr>
							<th style="width:160px">Fecha</th>
							<th style="width:80px">Nivel</th>
							<th style="width:120px">Categoría</th>
							<th>Mensaje</th>
							<th style="width:80px">Detalle</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $entries as $i => $e ) : ?>
						<tr class="gloq-log-row gloq-log-<?php echo esc_attr( $e['level'] ?? 'info' ); ?>">
							<td class="gloq-log-ts"><?php echo esc_html( $e['ts'] ?? '' ); ?></td>
							<td><span class="gloq-log-badge gloq-log-badge-<?php echo esc_attr( $e['level'] ?? 'info' ); ?>"><?php echo esc_html( strtoupper( $e['level'] ?? 'info' ) ); ?></span></td>
							<td><code><?php echo esc_html( $e['cat'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $e['msg'] ?? '' ); ?></td>
							<td>
								<?php if ( ! empty( $e['context'] ) ) : ?>
									<details><summary>Ver</summary>
										<pre style="white-space:pre-wrap;word-break:break-word;background:#f6f7f7;padding:8px 10px;border-radius:4px;font-size:11px;margin:6px 0 0;max-width:400px"><?php echo esc_html( wp_json_encode( $e['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
									</details>
								<?php else : ?>
									<span style="color:#999">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<style>
		.gloq-logs-stats{display:flex;gap:8px;margin:16px 0;flex-wrap:wrap}
		.gloq-log-stat{padding:6px 14px;background:#f6f7f7;border-radius:4px;font-size:13px;color:#5a6470}
		.gloq-log-stat-error{background:#fdecea;color:#7a1d12}
		.gloq-log-stat-warn{background:#fff8e1;color:#665100}
		.gloq-log-stat-info{background:#e7f3ff;color:#0c5298}
		.gloq-log-stat-debug{background:#e2e3e5;color:#495057}
		.gloq-log-stat-total{background:#0a4d3a;color:#fff}
		.gloq-logs-filters{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:0 0 14px;display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap}
		.gloq-logs-filters label{display:flex;flex-direction:column;font-size:12px;color:#5a6470;font-weight:600;text-transform:uppercase;letter-spacing:0.4px;gap:4px}
		.gloq-logs-filters select{padding:6px 10px;border:1px solid #cbd2d9;border-radius:4px;font-size:13px;font-weight:400;text-transform:none;letter-spacing:0;color:#222}
		.gloq-logs-table td{vertical-align:top;padding:10px 12px;font-size:13px}
		.gloq-log-ts{font-family:monospace;font-size:12px;color:#5a6470;white-space:nowrap}
		.gloq-log-badge{display:inline-block;padding:2px 9px;border-radius:11px;font-size:10px;font-weight:700;letter-spacing:0.5px}
		.gloq-log-badge-error{background:#dc3545;color:#fff}
		.gloq-log-badge-warn{background:#f7b500;color:#fff}
		.gloq-log-badge-info{background:#17a2b8;color:#fff}
		.gloq-log-badge-debug{background:#6c757d;color:#fff}
		.gloq-logs-table tr.gloq-log-error{background:rgba(253,236,234,0.5)}
		.gloq-logs-table tr.gloq-log-warn{background:rgba(255,248,225,0.5)}
		</style>
		<?php
	}

	public function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );
		Glotracol_Quote_Logger::clear();
		Glotracol_Quote_Logger::info( 'logger', 'Log vaciado por admin', [ 'user' => get_current_user_id() ] );
		wp_safe_redirect( add_query_arg( 'gloq_log_msg', 'cleared', admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) );
		exit;
	}
}
