<?php
/**
 * Visitor Toolbar frontend.
 *
 * @package Elavira
 */

namespace Elavira\Toolbar;

use Elavira\Statement\StatementRepository;

/** Loads and renders the Toolbar only when enabled or explicitly previewed. */
final class FrontendToolbar {
	/**
	 * Statement repository.
	 *
	 * @var StatementRepository
	 */
	private $statements;

	/**
	 * Create frontend integration.
	 *
	 * @param StatementRepository $statements Statements.
	 */
	public function __construct( StatementRepository $statements ) {
		$this->statements = $statements;
	}

	/** Whether this request should show the toolbar. @return bool */
	public function is_active() {
		$settings = ToolbarSettings::get();
		if ( $settings['enabled'] ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Capability-protected, read-only preview.
		return isset( $_GET['elavira_toolbar_preview'] ) && current_user_can( \Elavira\Core\Capabilities\Capability::MANAGE_TOOLBAR );
	}

	/** Enqueue small, independent public assets. @return void */
	public function enqueue() {
		if ( is_admin() || ! $this->is_active() ) {
			return;
		}
		wp_enqueue_style( 'elavira-toolbar', ELAVIRA_URL . 'assets/css/toolbar.css', array(), ELAVIRA_VERSION . '-titles-1' );
		wp_enqueue_script( 'elavira-toolbar', ELAVIRA_URL . 'assets/js/toolbar.js', array(), ELAVIRA_VERSION, true );
	}

	/** Apply persisted preferences before the body paints where practical. @return void */
	public function early_state() {
		if ( is_admin() || ! $this->is_active() ) {
			return;
		}
		$enabled = array_keys( array_filter( ToolbarSettings::get()['controls'] ) );
		echo '<script id="elavira-toolbar-state">(function(){try{var a=' . wp_json_encode( $enabled ) . ',s=JSON.parse(localStorage.getItem("elaviraToolbarPreferences")||"{}"),r=document.documentElement;if(a.indexOf("text_size")>-1&&[110,120,130,140,150].indexOf(s.textSize)>-1){r.style.setProperty("--elavira-text-size",s.textSize+"%");r.classList.add("elavira-pref-text-size")}var m={textSpacing:"text_spacing",lineHeight:"line_height",highContrast:"high_contrast",grayscale:"grayscale",highlightLinks:"highlight_links",enhancedFocus:"enhanced_focus",readingGuide:"reading_guide",readableFont:"readable_font"};Object.keys(m).forEach(function(k){if(a.indexOf(m[k])>-1&&s[k]===true)r.classList.add("elavira-pref-"+k.replace(/[A-Z]/g,function(x){return"-"+x.toLowerCase()}))});if(a.indexOf("appearance")>-1&&["light","dark"].indexOf(s.appearance)>-1)r.classList.add("elavira-pref-appearance-"+s.appearance)}catch(e){}})();</script>';
	}

	/** Render accessible trigger and modal. @return void */
	public function render() {
		if ( is_admin() || ! $this->is_active() ) {
			return;
		}
		$settings  = ToolbarSettings::get();
		$statement = $this->statements->find();
		$url       = $statement && 'published' === $statement['status'] && ! empty( $statement['post_id'] ) ? get_permalink( $statement['post_id'] ) : '';
		$labels    = array(
			'text_spacing'    => __( 'Increase text spacing', 'elavira' ),
			'line_height'     => __( 'Increase line height', 'elavira' ),
			'high_contrast'   => __( 'Use high contrast', 'elavira' ),
			'grayscale'       => __( 'Use grayscale', 'elavira' ),
			'highlight_links' => __( 'Highlight links', 'elavira' ),
			'enhanced_focus'  => __( 'Enhance keyboard focus', 'elavira' ),
			'reading_guide'   => __( 'Show reading guide', 'elavira' ),
			'readable_font'   => __( 'Use dyslexia-friendly font', 'elavira' ),
		);
		$state_ids = array(
			'text_spacing'    => 'textSpacing',
			'line_height'     => 'lineHeight',
			'high_contrast'   => 'highContrast',
			'grayscale'       => 'grayscale',
			'highlight_links' => 'highlightLinks',
			'enhanced_focus'  => 'enhancedFocus',
			'reading_guide'   => 'readingGuide',
			'readable_font'   => 'readableFont',
		);
		?>
		<div class="elavira-toolbar elavira-toolbar--<?php echo esc_attr( str_replace( '_', '-', $settings['position'] ) ); ?>" data-elavira-toolbar>
		<button type="button" class="elavira-toolbar__trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="elavira-toolbar-dialog"><?php echo esc_html__( 'Accessibility Preferences', 'elavira' ); ?></button>
		<div id="elavira-toolbar-dialog" class="elavira-toolbar__dialog" role="dialog" aria-modal="true" aria-labelledby="elavira-toolbar-title" hidden>
		<div class="elavira-toolbar__panel"><div class="elavira-toolbar__header"><h2 id="elavira-toolbar-title"><?php echo esc_html__( 'Accessibility Preferences', 'elavira' ); ?></h2><button type="button" class="elavira-toolbar__close" data-elavira-close><?php echo esc_html__( 'Close', 'elavira' ); ?></button></div>
		<p><?php echo esc_html__( 'Adjust browsing preferences for this device. These options do not fix accessibility problems on the website.', 'elavira' ); ?></p>
		<?php
		if ( $settings['controls']['text_size'] ) :
			?>
			<fieldset><legend><?php echo esc_html__( 'Text Size', 'elavira' ); ?></legend><button type="button" data-elavira-text-decrease><?php echo esc_html__( 'Decrease', 'elavira' ); ?></button> <output data-elavira-text-output>100%</output> <button type="button" data-elavira-text-increase><?php echo esc_html__( 'Increase', 'elavira' ); ?></button></fieldset><?php endif; ?>
		<?php
		if ( $settings['controls']['appearance'] ) :
			?>
			<fieldset><legend><?php echo esc_html__( 'Appearance', 'elavira' ); ?></legend>
			<?php
			foreach ( array(
				'default' => __( 'Default', 'elavira' ),
				'light'   => __( 'Light', 'elavira' ),
				'dark'    => __( 'Dark', 'elavira' ),
			) as $value => $label ) :
				?>
			<label><input type="radio" name="elavira-appearance" value="<?php echo esc_attr( $value ); ?>"> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset><?php endif; ?>
		<fieldset><legend><?php echo esc_html__( 'Reading and display preferences', 'elavira' ); ?></legend>
		<?php
		foreach ( $labels as $id => $label ) :
			if ( $settings['controls'][ $id ] ) :
				?>
			<label><input type="checkbox" data-elavira-pref="<?php echo esc_attr( $state_ids[ $id ] ); ?>"> <?php echo esc_html( $label ); ?></label>
				<?php
endif;
endforeach;
		?>
		</fieldset>
		<?php if ( $settings['controls']['readable_font'] ) : ?>
		<p class="elavira-toolbar__help"><?php echo esc_html__( 'The alternative font is an optional reading preference, not a cure or accessibility guarantee.', 'elavira' ); ?></p>
		<?php endif; ?>
		<div class="elavira-toolbar__actions"><button type="button" data-elavira-reset><?php echo esc_html__( 'Reset Preferences', 'elavira' ); ?></button>
		<?php
		if ( $url ) :
			?>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Accessibility Statement', 'elavira' ); ?></a><?php endif; ?></div>
		</div></div><div class="elavira-reading-guide" data-elavira-reading-guide hidden aria-hidden="true"></div></div>
		<?php
	}
}
