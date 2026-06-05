<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Actualizador remoto autocontenido (sin librerías externas).
 *
 * Hace que WordPress muestre "Actualización disponible" y actualice el plugin con
 * un clic, tomando la última versión publicada como Release/tag en el repositorio
 * público de GitHub. El flujo de publicación es: subir la versión en el código,
 * `git tag vX.Y.Z && git push --tags` (o crear un Release). No requiere subir ZIP
 * a mano: se usa el ZIP que GitHub genera del tag.
 *
 * Usa solo APIs nativas de WordPress:
 *  - pre_set_site_transient_update_plugins : inyecta la actualización si hay versión nueva.
 *  - plugins_api                            : alimenta la ventana "Ver detalles".
 *  - upgrader_source_selection              : renombra la carpeta del ZIP de GitHub al slug.
 */
class Glotracol_Quote_Updater {

	const GH_OWNER  = 'neracosu';
	const GH_REPO   = 'Cotizador-Glotracol';
	const CACHE_KEY = 'glotracol_quote_update_check';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** @var string glotracol-quote/glotracol-quote.php */
	private $basename;
	/** @var string glotracol-quote */
	private $slug;

	public function __construct() {
		$this->basename = GLOTRACOL_QUOTE_BASENAME;
		$this->slug     = dirname( $this->basename );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );
		// Limpia la caché tras actualizar para no re-ofrecer la misma versión.
		add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ], 10, 0 );
	}

	/**
	 * Última versión publicada en GitHub (cacheada). Prefiere el Release "latest";
	 * si no hay releases, usa el tag más reciente.
	 *
	 * @return array{version:string,zip:string,url:string,published:string,body:string}|null
	 */
	private function get_remote() {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached === 'none' ) return null;
		if ( is_array( $cached ) ) return $cached;

		$info = $this->fetch_json( sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GH_OWNER, self::GH_REPO ) );

		if ( ! is_array( $info ) || empty( $info['tag_name'] ) ) {
			$tags = $this->fetch_json( sprintf( 'https://api.github.com/repos/%s/%s/tags', self::GH_OWNER, self::GH_REPO ) );
			// La API no garantiza orden por versión: elegir el tag con la versión más alta.
			$best = null;
			if ( is_array( $tags ) ) {
				foreach ( $tags as $t ) {
					if ( empty( $t['name'] ) ) continue;
					$v = ltrim( (string) $t['name'], 'vV' );
					if ( $best === null || version_compare( $v, ltrim( (string) $best['name'], 'vV' ), '>' ) ) {
						$best = $t;
					}
				}
			}
			if ( $best ) {
				$info = [
					'tag_name'     => $best['name'],
					'zipball_url'  => $best['zipball_url'] ?? '',
					'html_url'     => sprintf( 'https://github.com/%s/%s/releases', self::GH_OWNER, self::GH_REPO ),
					'body'         => '',
					'published_at' => '',
					'assets'       => [],
				];
			}
		}

		if ( ! is_array( $info ) || empty( $info['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, 'none', self::CACHE_TTL );
			return null;
		}

		// Si el Release trae un asset .zip propio, preferirlo; si no, el zipball del código.
		$zip = $info['zipball_url'] ?? '';
		if ( ! empty( $info['assets'] ) && is_array( $info['assets'] ) ) {
			foreach ( $info['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && substr( (string) $asset['name'], -4 ) === '.zip' ) {
					$zip = $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = [
			'version'   => ltrim( (string) $info['tag_name'], 'vV' ),
			'zip'       => (string) $zip,
			'url'       => (string) ( $info['html_url'] ?? '' ),
			'published' => (string) ( $info['published_at'] ?? '' ),
			'body'      => (string) ( $info['body'] ?? '' ),
		];
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	private function fetch_json( $url ) {
		$res = wp_remote_get( $url, [
			'timeout' => 12,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Glotracol-Quote-Updater',
			],
		] );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $body ) ? $body : null;
	}

	/** Inyecta la actualización en el transient si la versión remota es mayor. */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote();
		if ( ! $remote || $remote['version'] === '' || $remote['zip'] === '' ) {
			return $transient;
		}

		$item = (object) [
			'slug'        => $this->slug,
			'plugin'      => $this->basename,
			'new_version' => $remote['version'],
			'url'         => $remote['url'],
			'package'     => $remote['zip'],
		];

		if ( version_compare( $remote['version'], GLOTRACOL_QUOTE_VERSION, '>' ) ) {
			$transient->response[ $this->basename ] = $item;
		} else {
			$item->new_version = GLOTRACOL_QUOTE_VERSION;
			$item->package     = '';
			$transient->no_update[ $this->basename ] = $item;
		}
		return $transient;
	}

	/** Alimenta la ventana "Ver detalles" del plugin. */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$remote = $this->get_remote();
		if ( ! $remote ) {
			return $result;
		}
		$info = (object) [
			'name'          => 'Glotracol Cotizador',
			'slug'          => $this->slug,
			'version'       => $remote['version'],
			'author'        => '<a href="https://neracosu.com/">Neracosu</a>',
			'homepage'      => $remote['url'],
			'download_link' => $remote['zip'],
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'sections'      => [
				'description' => 'Convierte WooCommerce en un sistema de solicitud de cotizaciones (RFQ) B2B.',
				'changelog'   => $this->changelog_html( $remote['body'] ),
			],
		];
		if ( $remote['published'] !== '' ) {
			$info->last_updated = $remote['published'];
		}
		return $info;
	}

	private function changelog_html( $body ) {
		$body = trim( (string) $body );
		if ( $body === '' ) {
			return 'Consulta la sección <strong>Novedades</strong> del plugin para el detalle de cada versión.';
		}
		return wpautop( wp_kses_post( $body ) );
	}

	/**
	 * El ZIP de GitHub se extrae en una carpeta con nombre del repo y el commit
	 * (p. ej. "neracosu-Cotizador-Glotracol-ab12cd3/"). WordPress necesita que la
	 * carpeta se llame igual que el slug del plugin ("glotracol-quote"), si no,
	 * instalaría una copia paralela. Aquí la renombramos.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}
		$desired = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}
		return $source;
	}

	public function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
