<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Admin_Settings {

	const OPTION_KEY = 'glotracol_quote_settings';
	const PAGE_SLUG  = 'glotracol-quote-settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Configuración del cotizador',
			'Configuración',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'glotracol_quote_group', self::OPTION_KEY, [ $this, 'sanitize' ] );
	}

	public function sanitize( $input ) {
		$existing = glotracol_quote_get_settings();
		if ( ! is_array( $input ) ) $input = [];
		$out = [];
		$out['destination_emails']       = sanitize_text_field( $input['destination_emails'] ?? $existing['destination_emails'] );
		$out['bcc_emails']               = sanitize_text_field( $input['bcc_emails'] ?? '' );
		$out['sender_name']              = sanitize_text_field( $input['sender_name'] ?? $existing['sender_name'] );
		$out['sender_email']             = is_email( $input['sender_email'] ?? '' ) ? sanitize_email( $input['sender_email'] ) : $existing['sender_email'];
		$out['admin_subject']            = sanitize_text_field( $input['admin_subject'] ?? $existing['admin_subject'] );
		$out['customer_subject']         = sanitize_text_field( $input['customer_subject'] ?? $existing['customer_subject'] );
		$out['customer_intro']           = sanitize_textarea_field( $input['customer_intro'] ?? $existing['customer_intro'] );
		$out['admin_intro']              = sanitize_textarea_field( $input['admin_intro'] ?? $existing['admin_intro'] );
		$out['form_intro']               = sanitize_textarea_field( $input['form_intro'] ?? $existing['form_intro'] );
		$out['terms_text']               = sanitize_textarea_field( $input['terms_text'] ?? $existing['terms_text'] );
		$out['thanks_message']           = sanitize_textarea_field( $input['thanks_message'] ?? $existing['thanks_message'] );
		$out['webhook_url']              = esc_url_raw( $input['webhook_url'] ?? '' );
		$out['webhook_secret']           = sanitize_text_field( $input['webhook_secret'] ?? '' );
		$out['rate_limit_per_hour']      = max( 0, (int) ( $input['rate_limit_per_hour'] ?? 3 ) );
		$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? 'yes' : 'no';

		// F3 + F4 — reglas de clasificación y alerta de pedidos grandes
		$out['size_threshold_medium_units'] = max( 1, (int) ( $input['size_threshold_medium_units'] ?? 25 ) );
		$out['size_threshold_large_units']  = max( $out['size_threshold_medium_units'] + 1, (int) ( $input['size_threshold_large_units'] ?? 80 ) );
		$out['size_threshold_medium_skus']  = max( 1, (int) ( $input['size_threshold_medium_skus'] ?? 5 ) );
		$out['size_threshold_large_skus']   = max( $out['size_threshold_medium_skus'] + 1, (int) ( $input['size_threshold_large_skus'] ?? 12 ) );
		$out['large_alert_enabled']         = ! empty( $input['large_alert_enabled'] ) ? 'yes' : 'no';
		$out['large_alert_email']           = is_email( $input['large_alert_email'] ?? '' ) ? sanitize_email( $input['large_alert_email'] ) : '';
		$out['auto_respond_enabled']        = ! empty( $input['auto_respond_enabled'] ) ? 'yes' : 'no';
		$out['appearance_inherit_elementor'] = ! empty( $input['appearance_inherit_elementor'] ) ? 'yes' : 'no';
		$slot = sanitize_key( $input['appearance_elementor_slot'] ?? 'primary' );
		$out['appearance_elementor_slot'] = in_array( $slot, [ 'primary', 'secondary', 'accent' ], true ) ? $slot : 'primary';
		$out['mini_cart_enabled'] = ! empty( $input['mini_cart_enabled'] ) ? 'yes' : 'no';
		$mcp = sanitize_key( $input['mini_cart_position'] ?? 'bottom-left' );
		$out['mini_cart_position'] = in_array( $mcp, [ 'bottom-left', 'bottom-right', 'top-left', 'top-right' ], true ) ? $mcp : 'bottom-left';

		// SMTP
		$out['smtp_enabled']     = ! empty( $input['smtp_enabled'] ) ? 'yes' : 'no';
		$out['smtp_host']        = sanitize_text_field( $input['smtp_host'] ?? '' );
		$out['smtp_port']        = preg_replace( '/[^0-9]/', '', $input['smtp_port'] ?? '587' );
		$enc = strtolower( sanitize_text_field( $input['smtp_encryption'] ?? 'tls' ) );
		$out['smtp_encryption']  = in_array( $enc, [ 'none', 'tls', 'ssl' ], true ) ? $enc : 'tls';
		$out['smtp_username']    = sanitize_text_field( $input['smtp_username'] ?? '' );
		// Si el password viene vacío, conservar el actual (UI envía '' cuando no se modifica)
		$incoming_pwd = (string) ( $input['smtp_password'] ?? '' );
		$out['smtp_password']    = $incoming_pwd !== '' ? $incoming_pwd : ( $existing['smtp_password'] ?? '' );
		$out['smtp_from_name']   = sanitize_text_field( $input['smtp_from_name'] ?? '' );
		$out['smtp_from_email']  = is_email( $input['smtp_from_email'] ?? '' ) ? sanitize_email( $input['smtp_from_email'] ) : '';
		return $out;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = glotracol_quote_get_settings();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$base_url = admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1>Configuración del cotizador</h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=general' ); ?>">General</a>
				<a class="nav-tab <?php echo $tab === 'emails' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=emails' ); ?>">Emails</a>
				<a class="nav-tab <?php echo $tab === 'form' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=form' ); ?>">Formulario</a>
				<a class="nav-tab <?php echo $tab === 'smtp' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=smtp' ); ?>">SMTP</a>
				<a class="nav-tab <?php echo $tab === 'integrations' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=integrations' ); ?>">Integraciones</a>
				<a class="nav-tab <?php echo $tab === 'rules' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=rules' ); ?>">Reglas</a>
				<a class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=appearance' ); ?>">Apariencia</a>
				<a class="nav-tab <?php echo $tab === 'advanced' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&tab=advanced' ); ?>">Avanzado</a>
			</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'glotracol_quote_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[__tab]" value="<?php echo esc_attr( $tab ); ?>">
				<?php $this->render_tab( $tab, $s ); ?>
				<?php submit_button(); ?>
			</form>
			<hr>
			<p><strong>Placeholders disponibles en asuntos y cuerpos:</strong> <code>{quote_id}</code> · <code>{customer_name}</code> · <code>{customer_email}</code> · <code>{customer_phone}</code> · <code>{customer_company}</code> · <code>{site_name}</code></p>
		</div>
		<?php
	}

	private function render_tab( $tab, $s ) {
		$opt = self::OPTION_KEY;
		switch ( $tab ) {
			case 'general':
				?>
				<table class="form-table">
					<tr><th><label>Email(s) destino interno</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[destination_emails]" value="<?php echo esc_attr( $s['destination_emails'] ); ?>">
						<p class="description">Separa varios emails con coma. Llegan a este buzón cada cotización.</p></td></tr>
					<tr><th><label>BCC (copia oculta)</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[bcc_emails]" value="<?php echo esc_attr( $s['bcc_emails'] ); ?>">
						<p class="description">Opcional. Separa con coma.</p></td></tr>
					<tr><th><label>Nombre del remitente</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[sender_name]" value="<?php echo esc_attr( $s['sender_name'] ); ?>"></td></tr>
					<tr><th><label>Email del remitente</label></th>
						<td><input type="email" class="regular-text" name="<?php echo $opt; ?>[sender_email]" value="<?php echo esc_attr( $s['sender_email'] ); ?>">
						<p class="description">Idealmente del dominio glotracol.com para mejor entregabilidad.</p></td></tr>
				</table>
				<?php
				break;

			case 'emails':
				?>
				<h2>Email al equipo (admin)</h2>
				<table class="form-table">
					<tr><th><label>Asunto</label></th>
						<td><input type="text" class="large-text" name="<?php echo $opt; ?>[admin_subject]" value="<?php echo esc_attr( $s['admin_subject'] ); ?>"></td></tr>
					<tr><th><label>Texto introductorio</label></th>
						<td><textarea class="large-text" rows="3" name="<?php echo $opt; ?>[admin_intro]"><?php echo esc_textarea( $s['admin_intro'] ); ?></textarea></td></tr>
				</table>
				<h2>Email al cliente (confirmación)</h2>
				<table class="form-table">
					<tr><th><label>Asunto</label></th>
						<td><input type="text" class="large-text" name="<?php echo $opt; ?>[customer_subject]" value="<?php echo esc_attr( $s['customer_subject'] ); ?>"></td></tr>
					<tr><th><label>Texto introductorio</label></th>
						<td><textarea class="large-text" rows="4" name="<?php echo $opt; ?>[customer_intro]"><?php echo esc_textarea( $s['customer_intro'] ); ?></textarea></td></tr>
				</table>
				<?php
				break;

			case 'form':
				?>
				<table class="form-table">
					<tr><th><label>Texto introductorio del formulario</label></th>
						<td><textarea class="large-text" rows="3" name="<?php echo $opt; ?>[form_intro]"><?php echo esc_textarea( $s['form_intro'] ); ?></textarea></td></tr>
					<tr><th><label>Texto del checkbox de aceptación</label></th>
						<td><textarea class="large-text" rows="2" name="<?php echo $opt; ?>[terms_text]"><?php echo esc_textarea( $s['terms_text'] ); ?></textarea></td></tr>
					<tr><th><label>Mensaje de la página de gracias</label></th>
						<td><textarea class="large-text" rows="3" name="<?php echo $opt; ?>[thanks_message]"><?php echo esc_textarea( $s['thanks_message'] ); ?></textarea></td></tr>
				</table>
				<?php
				break;

			case 'smtp':
				$external = Glotracol_Quote_SMTP::detect_external_smtp();
				?>
				<?php if ( $external ) : ?>
					<div class="notice notice-info inline" style="padding:12px 16px;border-left-color:#2271b1;margin:0 0 16px">
						<p style="margin:0"><strong>Detectamos <?php echo esc_html( $external ); ?> activo</strong> en el sitio. Ese plugin ya está manejando el envío SMTP de los emails. <strong>No es necesario</strong> que actives SMTP propio aquí — los correos del cotizador ya pasan por <?php echo esc_html( $external ); ?>.</p>
						<p style="margin:6px 0 0;color:#666;font-size:13px">Si quieres forzar la configuración de SMTP de este plugin (ignorando <?php echo esc_html( $external ); ?>), activa el toggle abajo. <em>No recomendado salvo que sepas lo que haces.</em></p>
					</div>
				<?php else : ?>
					<div class="notice notice-warning inline" style="padding:12px 16px;border-left-color:#dba617;margin:0 0 16px">
						<p style="margin:0"><strong>No detectamos ningún plugin SMTP activo.</strong> Los emails se están enviando con la función <code>mail()</code> del servidor, lo cual puede causar que lleguen a SPAM o no se entreguen. <strong>Recomendamos configurar SMTP aquí abajo.</strong></p>
					</div>
				<?php endif; ?>

				<table class="form-table">
					<tr><th><label>Activar SMTP propio</label></th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[smtp_enabled]" value="yes" <?php checked( $s['smtp_enabled'], 'yes' ); ?>> Enviar todos los emails de WordPress vía este servidor SMTP</label>
						<p class="description">Si está activo, sustituye al sistema de envío por defecto de WordPress.</p></td></tr>
					<tr><th><label>Servidor SMTP (host)</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[smtp_host]" value="<?php echo esc_attr( $s['smtp_host'] ); ?>" placeholder="smtp.hostinger.com">
						<p class="description">Hostinger: <code>smtp.hostinger.com</code> · Gmail: <code>smtp.gmail.com</code> · Office 365: <code>smtp.office365.com</code></p></td></tr>
					<tr><th><label>Puerto</label></th>
						<td><input type="number" name="<?php echo $opt; ?>[smtp_port]" value="<?php echo esc_attr( $s['smtp_port'] ); ?>" min="1" max="65535" style="width:120px">
						<p class="description">Habitualmente: <strong>587</strong> (TLS) o <strong>465</strong> (SSL).</p></td></tr>
					<tr><th><label>Encriptación</label></th>
						<td>
							<select name="<?php echo $opt; ?>[smtp_encryption]">
								<option value="tls" <?php selected( $s['smtp_encryption'], 'tls' ); ?>>TLS (recomendado)</option>
								<option value="ssl" <?php selected( $s['smtp_encryption'], 'ssl' ); ?>>SSL</option>
								<option value="none" <?php selected( $s['smtp_encryption'], 'none' ); ?>>Ninguna</option>
							</select>
						</td></tr>
					<tr><th><label>Usuario SMTP</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[smtp_username]" value="<?php echo esc_attr( $s['smtp_username'] ); ?>" placeholder="contacto@glotracol.com">
						<p class="description">Normalmente es la dirección de correo completa.</p></td></tr>
					<tr><th><label>Contraseña SMTP</label></th>
						<td><input type="password" class="regular-text" name="<?php echo $opt; ?>[smtp_password]" value="" placeholder="<?php echo $s['smtp_password'] !== '' ? '•••••••• (sin cambios)' : ''; ?>" autocomplete="new-password">
						<p class="description">Para Gmail necesitas crear una <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> (requiere 2FA). Deja vacío si no quieres modificar la actual.</p></td></tr>
					<tr><th><label>Nombre del remitente (From)</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[smtp_from_name]" value="<?php echo esc_attr( $s['smtp_from_name'] ); ?>" placeholder="Glotracol"></td></tr>
					<tr><th><label>Email del remitente (From)</label></th>
						<td><input type="email" class="regular-text" name="<?php echo $opt; ?>[smtp_from_email]" value="<?php echo esc_attr( $s['smtp_from_email'] ); ?>" placeholder="contacto@glotracol.com">
						<p class="description">Idealmente del mismo dominio del servidor SMTP para mejor entregabilidad.</p></td></tr>
				</table>

				<h2>Probar envío</h2>
				<p>Después de guardar los cambios, envía un email de prueba para verificar:</p>
				<table class="form-table">
					<tr><th><label>Enviar test a</label></th>
						<td>
							<input type="email" id="gloq-smtp-test-to" class="regular-text" placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
							<button type="button" id="gloq-smtp-test-btn" class="button button-secondary">Enviar email de prueba</button>
							<span id="gloq-smtp-test-result" style="margin-left:10px"></span>
						</td></tr>
				</table>
				<?php
				break;

			case 'integrations':
				?>
				<table class="form-table">
					<tr><th><label>Webhook URL</label></th>
						<td><input type="url" class="large-text" name="<?php echo $opt; ?>[webhook_url]" value="<?php echo esc_attr( $s['webhook_url'] ); ?>" placeholder="https://hook.eu1.make.com/abc123">
						<p class="description">URL a la que se enviará un POST JSON cuando se cree una cotización (Make/Zapier/n8n/Goja).</p></td></tr>
					<tr><th><label>Webhook secret</label></th>
						<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[webhook_secret]" value="<?php echo esc_attr( $s['webhook_secret'] ); ?>">
						<p class="description">Si se configura, el POST incluirá header <code>X-Glotracol-Signature: sha256=&lt;HMAC&gt;</code>.</p></td></tr>
				</table>
				<?php
				break;

			case 'rules':
				?>
				<h2>Clasificación automática del tamaño de la cotización</h2>
				<p class="description">El plugin etiqueta cada cotización como <strong>Pequeña</strong>, <strong>Mediana</strong> o <strong>Grande</strong> según los umbrales que definas aquí. Se aplica la categoría más alta entre el match por unidades totales y el match por productos distintos.</p>
				<table class="form-table">
					<tr><th><label>Mediana — desde N unidades</label></th>
						<td><input type="number" min="1" max="9999" name="<?php echo $opt; ?>[size_threshold_medium_units]" value="<?php echo esc_attr( $s['size_threshold_medium_units'] ?? 25 ); ?>" style="width:120px"> unidades totales en la cotización
						<p class="description">Cotizaciones con menos unidades quedan como <strong>Pequeña</strong>.</p></td></tr>
					<tr><th><label>Grande — desde N unidades</label></th>
						<td><input type="number" min="1" max="99999" name="<?php echo $opt; ?>[size_threshold_large_units]" value="<?php echo esc_attr( $s['size_threshold_large_units'] ?? 80 ); ?>" style="width:120px"> unidades totales
						<p class="description">Debe ser mayor que el umbral de mediana.</p></td></tr>
					<tr><th><label>Mediana — desde N productos distintos</label></th>
						<td><input type="number" min="1" max="500" name="<?php echo $opt; ?>[size_threshold_medium_skus]" value="<?php echo esc_attr( $s['size_threshold_medium_skus'] ?? 5 ); ?>" style="width:120px"> productos distintos (SKUs)</td></tr>
					<tr><th><label>Grande — desde N productos distintos</label></th>
						<td><input type="number" min="1" max="500" name="<?php echo $opt; ?>[size_threshold_large_skus]" value="<?php echo esc_attr( $s['size_threshold_large_skus'] ?? 12 ); ?>" style="width:120px"> productos distintos
						<p class="description">Debe ser mayor que el umbral de mediana.</p></td></tr>
				</table>

				<hr>

				<h2>Alerta para pedidos grandes</h2>
				<p class="description">Cuando una cotización quede clasificada como <strong>Grande</strong>, el email al equipo se envía con un template destacado (color rojo, badge "Atención prioritaria") y opcionalmente se copia a un correo adicional para asegurar respuesta rápida.</p>
				<table class="form-table">
					<tr><th><label>Activar alerta destacada</label></th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[large_alert_enabled]" value="yes" <?php checked( $s['large_alert_enabled'] ?? 'yes', 'yes' ); ?>> Usar template diferenciado y prefix "[GRANDE]" en el asunto</label></td></tr>
					<tr><th><label>Email destacado (opcional)</label></th>
						<td><input type="email" class="regular-text" name="<?php echo $opt; ?>[large_alert_email]" value="<?php echo esc_attr( $s['large_alert_email'] ?? '' ); ?>" placeholder="gerencia@glotracol.com">
						<p class="description">Si lo configuras, este correo recibirá una copia de las cotizaciones grandes (además de los emails configurados en General).</p></td></tr>
				</table>

				<hr>

				<h2>Auto-respuesta con cotización formal</h2>
				<p class="description">Cuando el cliente envía una cotización y <strong>todos sus SKUs tienen precio</strong> (B2B si su NIT está identificado, o público si no), el cliente recibe automáticamente un email con la cotización formal y los totales. Si falta precio para algún SKU, la cotización entra como <strong>"Pendiente de precios"</strong> y solo el equipo recibe la alerta para completar manualmente.</p>
				<table class="form-table">
					<tr><th><label>Activar auto-respuesta</label></th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[auto_respond_enabled]" value="yes" <?php checked( $s['auto_respond_enabled'] ?? 'yes', 'yes' ); ?>> Enviar cotización con precios automáticamente cuando todos los SKUs tienen precio</label>
						<p class="description">Si lo desactivas, el cliente siempre recibirá un email simple de confirmación y la respuesta formal será 100% manual.</p></td></tr>
				</table>
				<?php
				break;

			case 'appearance':
				?>
				<h2>Apariencia</h2>
				<p class="description">El plugin puede heredar el color principal de tu kit global de Elementor. Si lo dejas desactivado, usa el verde Glotracol.</p>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Heredar color de Elementor</th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[appearance_inherit_elementor]" value="yes" <?php checked( $s['appearance_inherit_elementor'] ?? 'no', 'yes' ); ?>> Usar el color global de Elementor como color de marca del plugin</label></td></tr>
					<tr><th scope="row">Slot de color de Elementor</th>
						<td>
							<select name="<?php echo $opt; ?>[appearance_elementor_slot]">
								<option value="primary" <?php selected( $s['appearance_elementor_slot'] ?? 'primary', 'primary' ); ?>>Primary</option>
								<option value="secondary" <?php selected( $s['appearance_elementor_slot'] ?? 'primary', 'secondary' ); ?>>Secondary</option>
								<option value="accent" <?php selected( $s['appearance_elementor_slot'] ?? 'primary', 'accent' ); ?>>Accent</option>
							</select>
							<p class="description">Qué color global de Elementor usar. Si el slot está vacío, cae al verde Glotracol.</p>
						</td></tr>
				</table>
				<h2>Carrito flotante</h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Mostrar carrito flotante</th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[mini_cart_enabled]" value="yes" <?php checked( $s['mini_cart_enabled'] ?? 'yes', 'yes' ); ?>> Burbuja visible en todo el sitio con lo añadido a la cotización</label></td></tr>
					<tr><th scope="row">Posición</th>
						<td>
							<select name="<?php echo $opt; ?>[mini_cart_position]">
								<option value="bottom-left" <?php selected( $s['mini_cart_position'] ?? 'bottom-left', 'bottom-left' ); ?>>Abajo izquierda</option>
								<option value="bottom-right" <?php selected( $s['mini_cart_position'] ?? 'bottom-left', 'bottom-right' ); ?>>Abajo derecha</option>
								<option value="top-left" <?php selected( $s['mini_cart_position'] ?? 'bottom-left', 'top-left' ); ?>>Arriba izquierda</option>
								<option value="top-right" <?php selected( $s['mini_cart_position'] ?? 'bottom-left', 'top-right' ); ?>>Arriba derecha</option>
							</select>
							<p class="description">Por defecto abajo-izquierda para no chocar con el botón de WhatsApp.</p>
						</td></tr>
				</table>
				<?php
				break;

			case 'advanced':
				?>
				<table class="form-table">
					<tr><th><label>Límite de envíos por hora (por IP)</label></th>
						<td><input type="number" min="0" max="100" name="<?php echo $opt; ?>[rate_limit_per_hour]" value="<?php echo esc_attr( $s['rate_limit_per_hour'] ); ?>">
						<p class="description">0 = sin límite. Recomendado: 3-5.</p></td></tr>
					<tr><th><label>Borrar datos al desinstalar</label></th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[delete_data_on_uninstall]" value="yes" <?php checked( $s['delete_data_on_uninstall'], 'yes' ); ?>> Eliminar todas las cotizaciones y configuraciones al desinstalar el plugin</label></td></tr>
				</table>
				<?php
				break;
		}
	}
}
