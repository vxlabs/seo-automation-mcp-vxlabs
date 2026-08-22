<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_smartforms_create_form', array( $this, 'create_form' ) );
		add_action( 'admin_post_smartforms_save_form', array( $this, 'save_form' ) );
		add_action( 'admin_post_smartforms_duplicate_form', array( $this, 'duplicate_form' ) );
		add_action( 'admin_post_smartforms_trash_form', array( $this, 'trash_form' ) );
		add_action( 'admin_post_smartforms_entry_status', array( $this, 'entry_status' ) );
		add_action( 'admin_post_smartforms_entry_note', array( $this, 'entry_note' ) );
		add_action( 'admin_post_smartforms_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_smartforms_export_entries', array( $this, 'export_entries' ) );
		add_action( 'admin_post_smartforms_save_integration', array( $this, 'save_integration' ) );
		add_action( 'admin_post_smartforms_delete_integration', array( $this, 'delete_integration' ) );
		add_action( 'admin_post_smartforms_spam_verdict', array( $this, 'spam_verdict' ) );
		add_action( 'admin_post_smartforms_retry_delivery', array( $this, 'retry_delivery' ) );
	}

	public function menu() {
		add_menu_page( 'SmartForms', 'SmartForms', 'smartforms_manage_forms', 'smartforms', array( $this, 'dashboard' ), 'dashicons-feedback', 30 );
		add_submenu_page( 'smartforms', 'Dashboard', 'Dashboard', 'smartforms_manage_forms', 'smartforms', array( $this, 'dashboard' ) );
		add_submenu_page( 'smartforms', 'Forms', 'Forms', 'smartforms_manage_forms', 'smartforms-forms', array( $this, 'forms' ) );
		add_submenu_page( null, 'Edit Form', 'Edit Form', 'smartforms_manage_forms', 'smartforms-form-edit', array( $this, 'form_editor' ) );
		add_submenu_page( 'smartforms', 'Entries', 'Entries', 'smartforms_view_entries', 'smartforms-entries', array( $this, 'entries' ) );
		add_submenu_page( 'smartforms', 'Sync', 'Sync', 'smartforms_view_delivery_logs', 'smartforms-sync', array( $this, 'sync' ) );
		add_submenu_page( 'smartforms', 'Settings', 'Settings', 'smartforms_manage_settings', 'smartforms-settings', array( $this, 'settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, 'smartforms' ) ) {
			return;
		}
		wp_enqueue_style( 'smartforms-admin', SMARTFORMS_URL . 'assets/css/admin.css', array(), SMARTFORMS_VERSION );
		wp_enqueue_style( 'smartforms-admin-sync', SMARTFORMS_URL . 'assets/css/admin-sync.css', array( 'smartforms-admin' ), SMARTFORMS_VERSION );
		wp_enqueue_script( 'smartforms-admin-sync', SMARTFORMS_URL . 'assets/js/admin-sync.js', array(), SMARTFORMS_VERSION, true );
		if ( 'admin_page_smartforms-form-edit' === $hook ) {
			wp_enqueue_style( 'smartforms-builder', SMARTFORMS_URL . 'assets/css/admin-builder.css', array( 'smartforms-admin' ), SMARTFORMS_VERSION );
			wp_enqueue_media();
			wp_enqueue_script( 'smartforms-builder', SMARTFORMS_URL . 'assets/js/admin-builder.js', array(), SMARTFORMS_VERSION, true );
			wp_localize_script( 'smartforms-builder', 'smartFormsBuilder', array( 'types' => SmartForms_Field_Registry::definitions() ) );
		}
	}

	public function dashboard() {
		$this->guard( 'smartforms_manage_forms' );
		$forms       = SmartForms_Form_Repository::list_forms();
		$stats       = current_user_can( 'smartforms_view_entries' ) ? SmartForms_Entry_Repository::stats() : array();
		$recent      = current_user_can( 'smartforms_view_entries' ) ? SmartForms_Entry_Repository::list_entries( array( 'per_page' => 5 ) ) : array( 'items' => array() );
		$today_count = current_user_can( 'smartforms_view_entries' ) ? SmartForms_Entry_Repository::count( array( 'date_from' => gmdate( 'Y-m-d' ) ) ) : 0;
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>SmartForms Dashboard</h1><?php $this->notice(); ?>
			<div class="smartforms-cards">
				<?php $this->card( 'Forms', count( $forms ) ); ?>
				<?php $this->card( 'Entries today', $today_count ); ?>
				<?php $this->card( 'New entries', isset( $stats['new'] ) ? $stats['new'] : 0 ); ?>
				<?php $this->card( 'Processed', isset( $stats['processed'] ) ? $stats['processed'] : 0 ); ?>
			</div>
			<div class="smartforms-panel"><h2>Recent entries</h2><?php $this->entries_table( $recent['items'], false ); ?></div>
		</div><?php
	}

	public function forms() {
		$this->guard( 'smartforms_manage_forms' );
		$forms = SmartForms_Form_Repository::list_forms( array( 'status' => array( 'publish', 'draft', 'trash' ) ) );
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1 class="wp-heading-inline">Forms</h1>
			<form class="smartforms-inline-create" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="smartforms_create_form"><?php wp_nonce_field( 'smartforms_create_form' ); ?>
				<input type="text" name="title" placeholder="New form name" required><button class="button button-primary">Add New Form</button>
			</form><hr class="wp-header-end"><?php $this->notice(); ?>
			<table class="wp-list-table widefat fixed striped"><thead><tr><th>Form</th><th>Status</th><th>Entries</th><th>Shortcode</th><th>Updated</th></tr></thead><tbody>
			<?php if ( ! $forms ) : ?><tr><td colspan="5">No forms yet.</td></tr><?php endif; ?>
			<?php foreach ( $forms as $form ) : ?>
				<tr><td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-form-edit&id=' . $form['id'] ) ); ?>"><?php echo esc_html( $form['title'] ); ?></a></strong>
					<div class="row-actions"><span><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-form-edit&id=' . $form['id'] ) ); ?>">Edit</a> | </span>
					<span><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smartforms_duplicate_form&id=' . $form['id'] ), 'smartforms_duplicate_' . $form['id'] ) ); ?>">Duplicate</a> | </span>
					<span class="trash"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smartforms_trash_form&id=' . $form['id'] ), 'smartforms_trash_' . $form['id'] ) ); ?>">Trash</a></span></div></td>
				<td><?php echo esc_html( ucfirst( $form['status'] ) ); ?></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-entries&form_id=' . $form['id'] ) ); ?>"><?php echo esc_html( $form['entry_count'] ); ?></a></td>
				<td><code><?php echo esc_html( $form['shortcode'] ); ?></code></td><td><?php echo esc_html( $form['modified_gmt'] ); ?> UTC</td></tr>
			<?php endforeach; ?></tbody></table>
		</div><?php
	}

	public function form_editor() {
		$this->guard( 'smartforms_manage_forms' );
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$form = SmartForms_Form_Repository::get( $id );
		if ( is_wp_error( $form ) ) {
			wp_die( esc_html( $form->get_error_message() ) );
		}
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>Edit Form</h1><?php $this->notice(); ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="smartforms-editor-form">
			<input type="hidden" name="action" value="smartforms_save_form"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'smartforms_save_' . $id ); ?>
			<div class="smartforms-editor-header"><input class="smartforms-title-input" type="text" name="title" value="<?php echo esc_attr( $form['title'] ); ?>" required>
			<select name="status"><option value="draft" <?php selected( $form['status'], 'draft' ); ?>>Draft</option><option value="publish" <?php selected( $form['status'], 'publish' ); ?>>Published</option></select><button class="button button-primary button-large">Save Form</button></div>
			<input type="hidden" id="smartforms-schema" name="schema" value="<?php echo esc_attr( wp_json_encode( $form['schema'] ) ); ?>">
			<div class="smartforms-builder-layout">
				<aside class="smartforms-palette"><h2>Fields</h2><p class="description">Drag a field into the canvas, or click to append it.</p><div id="smartforms-field-palette"></div></aside>
				<main class="smartforms-canvas"><div id="smartforms-field-list"></div><p class="smartforms-empty"><span class="dashicons dashicons-move" aria-hidden="true"></span><strong>Drag fields here</strong><span>Build your form by dropping fields from the left panel.</span></p></main>
				<aside class="smartforms-settings-panel"><h2>Form settings</h2><?php $this->form_settings_fields( $form['settings'] ); ?></aside>
			</div>
		</form></div><?php
	}

	private function form_settings_fields( $settings ) {
		$integrations = SmartForms_Integration_Repository::all( true );
		?>
		<input type="hidden" name="settings[show_title]" value="0"><label><input type="checkbox" name="settings[show_title]" value="1" <?php checked( $settings['show_title'] ); ?>> Show form title</label>
		<input type="hidden" name="settings[store_entries]" value="0"><label><input type="checkbox" name="settings[store_entries]" value="1" <?php checked( $settings['store_entries'] ); ?>> Store entries</label>
		<input type="hidden" name="settings[enable_recaptcha]" value="0"><label><input type="checkbox" name="settings[enable_recaptcha]" value="1" <?php checked( $settings['enable_recaptcha'] ); ?>> Enable configured reCAPTCHA</label>
		<label>After submission<select name="settings[confirmation_type]"><option value="message" <?php selected( $settings['confirmation_type'], 'message' ); ?>>Show message</option><option value="redirect" <?php selected( $settings['confirmation_type'], 'redirect' ); ?>>Redirect</option></select></label>
		<label>Confirmation message<textarea name="settings[confirmation_message]" rows="3"><?php echo esc_textarea( $settings['confirmation_message'] ); ?></textarea></label>
		<label>Redirect URL<input type="url" name="settings[redirect_url]" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>"></label>
		<label>Notification email<input type="email" name="settings[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>"></label>
		<label>Email subject<input type="text" name="settings[notification_subject]" value="<?php echo esc_attr( $settings['notification_subject'] ); ?>"><small>Use {form_title} in the subject.</small></label>
		<hr><h3>Sync destinations</h3><p class="description">Only clean entries are sent. Create destinations in the Sync tab.</p>
		<?php if ( ! $integrations ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-sync&section=destinations' ) ); ?>">Create an Email or API destination</a></p><?php endif; ?>
		<?php foreach ( $integrations as $integration ) : ?><label><input type="checkbox" name="settings[integration_ids][]" value="<?php echo esc_attr( $integration['id'] ); ?>" <?php checked( in_array( $integration['id'], (array) $settings['integration_ids'], true ) ); ?>> <?php echo esc_html( $integration['name'] . ' (' . strtoupper( $integration['type'] ) . ')' ); ?></label><?php endforeach; ?>
		<?php
	}

	public function entries() {
		$this->guard( 'smartforms_view_entries' );
		if ( ! empty( $_GET['entry_id'] ) ) {
			$this->entry_detail( absint( $_GET['entry_id'] ) );
			return;
		}
		$args = array(
			'form_id'  => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
			'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
			'spam_status'=>isset( $_GET['spam_status'] ) ? sanitize_key( $_GET['spam_status'] ) : '',
			'date_from'=> isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'  => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
			'per_page' => 20,
		);
		$result = SmartForms_Entry_Repository::list_entries( $args );
		$forms  = SmartForms_Form_Repository::list_forms();
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>Entries</h1><?php $this->notice(); ?>
		<form method="get" class="smartforms-filters"><input type="hidden" name="page" value="smartforms-entries">
		<select name="form_id"><option value="">All forms</option><?php foreach ( $forms as $form ) : ?><option value="<?php echo esc_attr( $form['id'] ); ?>" <?php selected( $args['form_id'], $form['id'] ); ?>><?php echo esc_html( $form['title'] ); ?></option><?php endforeach; ?></select>
		<select name="status"><option value="">All statuses</option><?php foreach ( SmartForms_Entry_Repository::STATUSES as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $args['status'], $status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></option><?php endforeach; ?></select>
		<select name="spam_status"><option value="">All spam states</option><?php foreach ( SmartForms_Spam_Service::STATUSES as $spam_status ) : ?><option value="<?php echo esc_attr( $spam_status ); ?>" <?php selected( $args['spam_status'], $spam_status ); ?>><?php echo esc_html( ucfirst( $spam_status ) ); ?></option><?php endforeach; ?></select>
		<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>"><input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>"><button class="button">Filter</button>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'smartforms_export_entries' ), array_filter( $args ) ), admin_url( 'admin-post.php' ) ), 'smartforms_export_entries' ) ); ?>">Export CSV</a></form>
		<?php $this->entries_table( $result['items'], true ); ?>
		<p><?php echo esc_html( $result['total'] ); ?> entries<?php if ( $result['has_more'] ) : ?> · <a href="<?php echo esc_url( add_query_arg( 'paged', $result['next_page'] ) ); ?>">Next page →</a><?php endif; ?></p></div><?php
	}

	private function entries_table( $entries, $show_actions ) {
		?><table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Form</th><th>First field</th><th>Workflow</th><th>Spam</th><th>Submitted</th></tr></thead><tbody>
		<?php if ( ! $entries ) : ?><tr><td colspan="6">No entries found.</td></tr><?php endif; ?>
		<?php foreach ( $entries as $entry ) : $first = reset( $entry['fields'] ); ?>
		<tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-entries&entry_id=' . $entry['id'] ) ); ?>">#<?php echo esc_html( $entry['id'] ); ?></a></td><td><?php echo esc_html( $entry['form_title'] ); ?></td><td><?php echo esc_html( $first ? $this->display_value( $first['value'] ) : '—' ); ?></td><td><span class="smartforms-status status-<?php echo esc_attr( $entry['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['status'] ) ) ); ?></span></td><td><span class="smartforms-status spam-<?php echo esc_attr( $entry['spam_status'] ); ?>"><?php echo esc_html( ucfirst( $entry['spam_status'] ) ); ?></span></td><td><?php echo esc_html( $entry['submitted_at'] ); ?> UTC</td></tr>
		<?php endforeach; ?></tbody></table><?php
	}

	private function entry_detail( $id ) {
		$entry = SmartForms_Entry_Repository::get( $id );
		if ( is_wp_error( $entry ) ) { wp_die( esc_html( $entry->get_error_message() ) ); }
		$events = SmartForms_Entry_Repository::events( $id );
		$deliveries = SmartForms_Delivery_Repository::list_deliveries( array( 'entry_id'=>$id, 'per_page'=>100 ) );
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>Entry #<?php echo esc_html( $id ); ?></h1><?php $this->notice(); ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-entries' ) ); ?>">← All entries</a></p>
		<div class="smartforms-entry-layout"><section class="smartforms-panel"><h2><?php echo esc_html( $entry['form_title'] ); ?></h2><dl class="smartforms-entry-values">
		<?php foreach ( $entry['fields'] as $field ) : ?><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo nl2br( esc_html( $this->display_value( $field['value'] ) ) ); ?></dd><?php endforeach; ?></dl></section>
		<aside><div class="smartforms-panel"><h2>Process entry</h2><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="smartforms_entry_status"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'smartforms_entry_status_' . $id ); ?>
		<select name="status"><?php foreach ( SmartForms_Entry_Repository::STATUSES as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $entry['status'], $status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></option><?php endforeach; ?></select> <button class="button button-primary">Update</button></form>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="smartforms_entry_note"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'smartforms_entry_note_' . $id ); ?><textarea name="note" rows="4" placeholder="Internal note" required></textarea><button class="button">Add note</button></form></div>
		<div class="smartforms-panel"><h2>Spam decision</h2><p>Current: <strong><?php echo esc_html( ucfirst( $entry['spam_status'] ) ); ?></strong><?php if ( $entry['classifier'] ) : ?><br><small><?php echo esc_html( $entry['classifier'] ); ?><?php echo $entry['spam_reason'] ? ': ' . esc_html( $entry['spam_reason'] ) : ''; ?></small><?php endif; ?></p><?php if ( current_user_can( 'smartforms_classify_entries' ) ) : ?><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="smartforms_spam_verdict"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'smartforms_spam_verdict_' . $id ); ?><textarea name="reason" rows="2" placeholder="Reason" required></textarea><button class="button button-primary" name="verdict" value="clean">Mark clean &amp; sync</button> <button class="button" name="verdict" value="spam">Mark spam</button></form><?php endif; ?></div>
		<div class="smartforms-panel"><h2>Deliveries</h2><?php if ( ! $deliveries['items'] ) : ?><p>No deliveries queued.</p><?php endif; ?><?php foreach ( $deliveries['items'] as $delivery ) : ?><p><strong><?php echo esc_html( $delivery['integration_name'] ?: 'Deleted destination' ); ?></strong> — <?php echo esc_html( ucfirst( $delivery['status'] ) ); ?><br><small><?php echo esc_html( $delivery['last_error'] ?: $delivery['updated_at'] . ' UTC' ); ?></small></p><?php endforeach; ?></div>
		<div class="smartforms-panel"><h2>Activity</h2><?php if ( ! $events ) : ?><p>No activity yet.</p><?php endif; ?><?php foreach ( $events as $event ) : ?><p><strong><?php echo esc_html( ucfirst( $event['event_type'] ) ); ?></strong> <?php echo esc_html( $event['note'] ?: trim( $event['old_status'] . ' → ' . $event['new_status'] ) ); ?><br><small><?php echo esc_html( $event['created_at'] ); ?> UTC</small></p><?php endforeach; ?></div></aside></div></div><?php
	}

	public function sync() {
		$this->guard( 'smartforms_view_delivery_logs' );
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'overview';
		$sections = array( 'overview'=>'Overview', 'destinations'=>'Destinations', 'spam'=>'Spam Review', 'deliveries'=>'Delivery Log' );
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>Sync</h1><?php $this->notice(); ?><nav class="nav-tab-wrapper smartforms-subtabs">
		<?php foreach ( $sections as $slug=>$label ) : ?><a class="nav-tab <?php echo $section===$slug?'nav-tab-active':''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=smartforms-sync&section='.$slug ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<?php
		if ( 'destinations' === $section ) $this->sync_destinations();
		elseif ( 'spam' === $section ) $this->sync_spam();
		elseif ( 'deliveries' === $section ) $this->sync_deliveries();
		else $this->sync_overview();
		echo '</div>';
	}

	private function sync_overview() {
		$pending=SmartForms_Entry_Repository::count( array('spam_status'=>'pending') ); $stats=SmartForms_Delivery_Repository::stats(); $destinations=SmartForms_Integration_Repository::all();
		?><div class="smartforms-cards"><?php $this->card('Pending spam review',$pending); $this->card('Active destinations',count(array_filter($destinations,function($i){return $i['enabled'];}))); $this->card('Queued / retrying',$stats['queued']+$stats['retry']); $this->card('Dead deliveries',$stats['dead']); ?></div>
		<div class="smartforms-panel"><h2>How gated sync works</h2><p>Every stored entry starts as <strong>Pending</strong>. Rule-based checks can immediately block obvious spam. ChatGPT, Claude, another MCP client, or an administrator then marks pending entries clean or spam. Only clean entries are added to the durable Email/API delivery queue.</p><p><code>smartforms_claim_spam_reviews</code> → classify → <code>smartforms_set_spam_verdict</code></p><p class="description">MCP clients are request-driven, so schedule your AI agent or call it from your automation platform. SmartForms intentionally fails closed while no classifier is running.</p></div><?php
	}

	private function sync_destinations() {
		if ( ! current_user_can( 'smartforms_manage_integrations' ) ) { echo '<div class="smartforms-panel"><p>You can view delivery activity, but only an administrator can change destinations.</p></div>'; return; }
		$edit=isset($_GET['id'])?SmartForms_Integration_Repository::get(absint($_GET['id'])):null; if(is_wp_error($edit))$edit=null; $type=$edit?$edit['type']:(isset($_GET['type'])&&'api'===$_GET['type']?'api':'email'); $c=$edit?$edit['config']:array();
		?><div class="smartforms-sync-grid"><section class="smartforms-panel"><h2><?php echo $edit?'Edit':'Add'; ?> <?php echo esc_html(strtoupper($type)); ?> destination</h2><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="smartforms_save_integration"><input type="hidden" name="id" value="<?php echo esc_attr($edit?$edit['id']:0); ?>"><?php wp_nonce_field('smartforms_save_integration'); ?><label>Name<input name="name" required value="<?php echo esc_attr($edit?$edit['name']:''); ?>"></label><label>Type<select name="type"><option value="email" <?php selected($type,'email'); ?>>Email / SMTP</option><option value="api" <?php selected($type,'api'); ?>>API webhook</option></select></label><label><input type="checkbox" name="enabled" value="1" <?php checked(!$edit||$edit['enabled']); ?>> Enabled</label>
		<h3>Email / SMTP</h3><label>Mailer<select name="config[mailer]"><option value="site" <?php selected(isset($c['mailer'])?$c['mailer']:'site','site'); ?>>WordPress site mailer</option><option value="smtp" <?php selected(isset($c['mailer'])?$c['mailer']:'','smtp'); ?>>Custom SMTP</option></select></label><label>Recipients<input name="config[recipients]" value="<?php echo esc_attr(isset($c['recipients'])?$c['recipients']:''); ?>" placeholder="team@example.com"></label><label>CC<input name="config[cc]" value="<?php echo esc_attr(isset($c['cc'])?$c['cc']:''); ?>"></label><label>BCC<input name="config[bcc]" value="<?php echo esc_attr(isset($c['bcc'])?$c['bcc']:''); ?>"></label><label>From email<input type="email" name="config[from_email]" value="<?php echo esc_attr(isset($c['from_email'])?$c['from_email']:''); ?>"></label><label>From name<input name="config[from_name]" value="<?php echo esc_attr(isset($c['from_name'])?$c['from_name']:''); ?>"></label><label>Reply-to<input type="email" name="config[reply_to]" value="<?php echo esc_attr(isset($c['reply_to'])?$c['reply_to']:''); ?>"></label><label>Subject<input name="config[subject]" value="<?php echo esc_attr(isset($c['subject'])?$c['subject']:'New submission: {form_title}'); ?>"></label><label>Body<textarea name="config[body]" rows="5"><?php echo esc_textarea(isset($c['body'])?$c['body']:"Entry #{entry_id}\n\n{fields}"); ?></textarea></label><label><input type="checkbox" name="config[html]" value="1" <?php checked(!empty($c['html'])); ?>> HTML email</label><label>SMTP host<input name="config[host]" value="<?php echo esc_attr(isset($c['host'])?$c['host']:''); ?>"></label><label>Port<input type="number" name="config[port]" value="<?php echo esc_attr(isset($c['port'])?$c['port']:587); ?>"></label><label>Encryption<select name="config[encryption]"><option value="tls" <?php selected(isset($c['encryption'])?$c['encryption']:'tls','tls'); ?>>TLS</option><option value="ssl" <?php selected(isset($c['encryption'])?$c['encryption']:'','ssl'); ?>>SSL</option><option value="none" <?php selected(isset($c['encryption'])?$c['encryption']:'','none'); ?>>None</option></select></label><label>SMTP username<input name="config[username]" value="<?php echo esc_attr(isset($c['username'])?$c['username']:''); ?>"></label><label>SMTP password<input type="password" name="config[password]" autocomplete="new-password" placeholder="<?php echo !empty($c['password_configured'])?'Saved — leave blank to keep':''; ?>"></label>
		<h3>API webhook</h3><label>HTTPS URL<input type="url" name="config[url]" value="<?php echo esc_attr(isset($c['url'])?$c['url']:''); ?>" placeholder="https://api.example.com/forms"></label><label>Authentication<select name="config[auth_type]"><?php foreach(array('none'=>'None','bearer'=>'Bearer token','api_key'=>'API key header','basic'=>'Basic auth','hmac'=>'HMAC SHA-256') as $v=>$label): ?><option value="<?php echo esc_attr($v); ?>" <?php selected(isset($c['auth_type'])?$c['auth_type']:'none',$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><label>Header name<input name="config[header_name]" value="<?php echo esc_attr(isset($c['header_name'])?$c['header_name']:'X-API-Key'); ?>"></label><label>Username<input name="config[username]" value="<?php echo esc_attr(isset($c['username'])?$c['username']:''); ?>"></label><label>Token / password / HMAC secret<input type="password" name="config[secret]" autocomplete="new-password" placeholder="<?php echo !empty($c['secret_configured'])?'Saved — leave blank to keep':''; ?>"></label><label>Timeout seconds<input type="number" min="3" max="30" name="config[timeout]" value="<?php echo esc_attr(isset($c['timeout'])?$c['timeout']:10); ?>"></label><p><button class="button button-primary">Save destination</button></p></form></section>
		<section class="smartforms-panel"><h2>Destinations</h2><?php foreach(SmartForms_Integration_Repository::all() as $i): ?><div class="smartforms-destination"><strong><?php echo esc_html($i['name']); ?></strong> <span class="smartforms-status"><?php echo esc_html(strtoupper($i['type'])); ?></span><p><?php echo $i['enabled']?'Enabled':'Disabled'; ?></p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=smartforms-sync&section=destinations&id='.$i['id'])); ?>">Edit</a> <a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=smartforms_delete_integration&id='.$i['id']),'smartforms_delete_integration_'.$i['id'])); ?>" onclick="return confirm('Delete this destination?');">Delete</a></div><?php endforeach; ?><?php if(!SmartForms_Integration_Repository::all()): ?><p>No destinations yet.</p><?php endif; ?></section></div><?php
	}

	private function sync_spam() { $result=SmartForms_Entry_Repository::list_entries(array('spam_status'=>'pending','per_page'=>100)); echo '<div class="smartforms-panel"><h2>Pending AI or manual review</h2>'; $this->entries_table($result['items'],true); echo '</div>'; }
	private function sync_deliveries() { $status=isset($_GET['status'])?sanitize_key($_GET['status']):''; $result=SmartForms_Delivery_Repository::list_deliveries(array('status'=>$status,'per_page'=>100)); ?><div class="smartforms-panel"><h2>Delivery log</h2><form method="get" class="smartforms-filters"><input type="hidden" name="page" value="smartforms-sync"><input type="hidden" name="section" value="deliveries"><select name="status"><option value="">All statuses</option><?php foreach(SmartForms_Delivery_Repository::STATUSES as $s): ?><option value="<?php echo esc_attr($s); ?>" <?php selected($status,$s); ?>><?php echo esc_html(ucfirst($s)); ?></option><?php endforeach; ?></select><button class="button">Filter</button></form><table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Entry</th><th>Destination</th><th>Status</th><th>Attempts</th><th>Response</th><th>Updated</th><th></th></tr></thead><tbody><?php if(!$result['items']): ?><tr><td colspan="8">No deliveries yet.</td></tr><?php endif; ?><?php foreach($result['items'] as $d): ?><tr><td>#<?php echo esc_html($d['id']); ?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=smartforms-entries&entry_id='.$d['entry_id'])); ?>">#<?php echo esc_html($d['entry_id']); ?></a></td><td><?php echo esc_html($d['integration_name']?:'Deleted'); ?></td><td><?php echo esc_html(ucfirst($d['status'])); ?></td><td><?php echo esc_html($d['attempt_count']); ?></td><td><?php echo esc_html($d['last_error']?:($d['response_code']?'HTTP '.$d['response_code']:'—')); ?></td><td><?php echo esc_html($d['updated_at']); ?> UTC</td><td><?php if(current_user_can('smartforms_retry_deliveries')&&in_array($d['status'],array('dead','retry'),true)): ?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=smartforms_retry_delivery&id='.$d['id']),'smartforms_retry_delivery_'.$d['id'])); ?>">Retry</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php }

	public function settings() {
		$this->guard( 'smartforms_manage_settings' );
		$settings = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$messages = wp_parse_args( $settings['messages'], SmartForms_Form_Repository::default_messages() );
		?>
		<div class="wrap smartforms-admin"><?php $this->top_navigation(); ?><h1>SmartForms Settings</h1><?php $this->notice(); ?><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="smartforms_save_settings"><?php wp_nonce_field( 'smartforms_save_settings' ); ?>
		<div class="smartforms-settings-grid"><section class="smartforms-panel"><h2>Google reCAPTCHA</h2>
		<label>Version<select name="recaptcha_mode"><option value="none" <?php selected( $settings['recaptcha_mode'], 'none' ); ?>>Disabled</option><option value="v2_checkbox" <?php selected( $settings['recaptcha_mode'], 'v2_checkbox' ); ?>>v2 checkbox</option><option value="v2_invisible" <?php selected( $settings['recaptcha_mode'], 'v2_invisible' ); ?>>v2 invisible</option><option value="v3" <?php selected( $settings['recaptcha_mode'], 'v3' ); ?>>v3 score</option></select></label>
		<label>Site key<input type="text" name="recaptcha_site" value="<?php echo esc_attr( $settings['recaptcha_site'] ); ?>" autocomplete="off"></label>
		<label>Secret key<input type="password" name="recaptcha_secret" value="" placeholder="<?php echo $settings['recaptcha_secret'] ? 'Saved — leave blank to keep' : ''; ?>" autocomplete="new-password"></label>
		<label>v3 minimum score<input type="number" name="recaptcha_score" min="0" max="1" step="0.1" value="<?php echo esc_attr( $settings['recaptcha_score'] ); ?>"></label></section>
		<section class="smartforms-panel"><h2>Privacy and retention</h2><label>Retain entries for days<input type="number" name="retain_days" min="0" max="3650" value="<?php echo esc_attr( $settings['retain_days'] ); ?>"></label><p class="description">Use 0 to keep entries until you trash them manually.</p><label><input type="checkbox" name="ip_logging" value="1" <?php checked( $settings['ip_logging'] ); ?>> Store a salted hash of submitter IPs</label><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>> Permanently delete SmartForms data when the plugin is uninstalled</label><p class="description">Raw IP addresses are never stored by SmartForms. Deactivation always preserves data.</p></section></div>
		<section class="smartforms-panel"><h2>Validation messages</h2><p>Tokens: <code>{label}</code>, <code>{min}</code>, <code>{max}</code>.</p><div class="smartforms-message-grid">
		<?php foreach ( $messages as $key => $message ) : ?><label><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?><input type="text" name="messages[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $message ); ?>"></label><?php endforeach; ?></div></section>
		<p><button class="button button-primary">Save Settings</button></p></form></div><?php
	}

	public function create_form() {
		$this->guard( 'smartforms_manage_forms' ); check_admin_referer( 'smartforms_create_form' );
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Untitled Form';
		$form  = SmartForms_Form_Repository::create( $title );
		if ( is_wp_error( $form ) ) { wp_die( esc_html( $form->get_error_message() ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=smartforms-form-edit&id=' . $form['id'] ) ); exit;
	}

	public function save_form() {
		$this->guard( 'smartforms_manage_forms' ); $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; check_admin_referer( 'smartforms_save_' . $id );
		$schema = isset( $_POST['schema'] ) ? json_decode( wp_unslash( $_POST['schema'] ), true ) : array();
		$result = SmartForms_Form_Repository::update( $id, array( 'title' => wp_unslash( $_POST['title'] ), 'status' => sanitize_key( $_POST['status'] ), 'schema' => $schema, 'settings' => isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array() ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		$this->redirect( 'admin.php?page=smartforms-form-edit&id=' . $id, 'saved' );
	}

	public function duplicate_form() {
		$this->guard( 'smartforms_manage_forms' ); $id = absint( $_GET['id'] ); check_admin_referer( 'smartforms_duplicate_' . $id ); $form = SmartForms_Form_Repository::duplicate( $id );
		if ( is_wp_error( $form ) ) { wp_die( esc_html( $form->get_error_message() ) ); } $this->redirect( 'admin.php?page=smartforms-form-edit&id=' . $form['id'], 'duplicated' );
	}

	public function trash_form() {
		$this->guard( 'smartforms_manage_forms' ); $id = absint( $_GET['id'] ); check_admin_referer( 'smartforms_trash_' . $id ); SmartForms_Form_Repository::update( $id, array( 'status' => 'trash' ) ); $this->redirect( 'admin.php?page=smartforms-forms', 'trashed' );
	}

	public function entry_status() {
		$this->guard( 'smartforms_manage_entries' ); $id = absint( $_POST['id'] ); check_admin_referer( 'smartforms_entry_status_' . $id ); SmartForms_Entry_Repository::update_status( $id, sanitize_key( $_POST['status'] ) ); $this->redirect( 'admin.php?page=smartforms-entries&entry_id=' . $id, 'updated' );
	}

	public function entry_note() {
		$this->guard( 'smartforms_manage_entries' ); $id = absint( $_POST['id'] ); check_admin_referer( 'smartforms_entry_note_' . $id ); SmartForms_Entry_Repository::add_note( $id, wp_unslash( $_POST['note'] ) ); $this->redirect( 'admin.php?page=smartforms-entries&entry_id=' . $id, 'noted' );
	}

	public function save_integration() {
		$this->guard( 'smartforms_manage_integrations' ); check_admin_referer( 'smartforms_save_integration' ); $id=isset($_POST['id'])?absint($_POST['id']):0;
		$result=SmartForms_Integration_Repository::save(array('name'=>isset($_POST['name'])?wp_unslash($_POST['name']):'','type'=>isset($_POST['type'])?sanitize_key($_POST['type']):'email','enabled'=>!empty($_POST['enabled']),'config'=>isset($_POST['config'])?wp_unslash($_POST['config']):array()),$id);
		if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); $this->redirect('admin.php?page=smartforms-sync&section=destinations','saved');
	}

	public function delete_integration() { $this->guard('smartforms_manage_integrations'); $id=isset($_GET['id'])?absint($_GET['id']):0; check_admin_referer('smartforms_delete_integration_'.$id); SmartForms_Integration_Repository::delete($id); $this->redirect('admin.php?page=smartforms-sync&section=destinations','deleted'); }
	public function spam_verdict() { $this->guard('smartforms_classify_entries'); $id=isset($_POST['id'])?absint($_POST['id']):0; check_admin_referer('smartforms_spam_verdict_'.$id); $result=SmartForms_Spam_Service::classify($id,isset($_POST['verdict'])?sanitize_key($_POST['verdict']):'',null,null,isset($_POST['reason'])?wp_unslash($_POST['reason']):'','manual:'.wp_get_current_user()->user_login); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); $this->redirect('admin.php?page=smartforms-entries&entry_id='.$id,'classified'); }
	public function retry_delivery() { $this->guard('smartforms_retry_deliveries'); $id=isset($_GET['id'])?absint($_GET['id']):0; check_admin_referer('smartforms_retry_delivery_'.$id); $result=SmartForms_Delivery_Repository::retry($id); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); $this->redirect('admin.php?page=smartforms-sync&section=deliveries','retried'); }

	public function save_settings() {
		$this->guard( 'smartforms_manage_settings' ); check_admin_referer( 'smartforms_save_settings' );
		$current  = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$defaults = SmartForms_Form_Repository::default_messages(); $messages = array();
		foreach ( $defaults as $key => $default ) { $messages[ $key ] = isset( $_POST['messages'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['messages'][ $key ] ) ) : $default; }
		$secret = isset( $_POST['recaptcha_secret'] ) ? trim( wp_unslash( $_POST['recaptcha_secret'] ) ) : '';
		$settings = array( 'messages' => $messages, 'recaptcha_mode' => in_array( $_POST['recaptcha_mode'], array( 'none', 'v2_checkbox', 'v2_invisible', 'v3' ), true ) ? $_POST['recaptcha_mode'] : 'none', 'recaptcha_site' => sanitize_text_field( wp_unslash( $_POST['recaptcha_site'] ) ), 'recaptcha_secret' => '' !== $secret ? sanitize_text_field( $secret ) : $current['recaptcha_secret'], 'recaptcha_score' => max( 0, min( 1, (float) $_POST['recaptcha_score'] ) ), 'retain_days' => min( 3650, absint( $_POST['retain_days'] ) ), 'ip_logging' => ! empty( $_POST['ip_logging'] ), 'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ) );
		update_option( 'smartforms_settings', $settings, false ); $this->redirect( 'admin.php?page=smartforms-settings', 'saved' );
	}

	public function export_entries() {
		$this->guard( 'smartforms_view_entries' ); check_admin_referer( 'smartforms_export_entries' );
		$args = array( 'form_id' => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0, 'status' => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', 'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '', 'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '', 'per_page' => 100 );
		header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=smartforms-entries-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); fputcsv( $out, array( 'Entry ID', 'Form', 'Status', 'Submitted UTC', 'Field', 'Value' ) ); $page = 1;
		do { $result = SmartForms_Entry_Repository::list_entries( array_merge( $args, array( 'page' => $page ) ) ); foreach ( $result['items'] as $entry ) { foreach ( $entry['fields'] as $field ) { fputcsv( $out, array_map( array( $this, 'csv_value' ), array( $entry['id'], $entry['form_title'], $entry['status'], $entry['submitted_at'], $field['label'], $this->display_value( $field['value'] ) ) ) ); } } $page++; } while ( $result['has_more'] );
		fclose( $out ); exit;
	}

	private function display_value( $value ) { if ( is_array( $value ) ) { return implode( ', ', $value ); } if ( is_bool( $value ) ) { return $value ? 'Yes' : 'No'; } return (string) $value; }
	private function csv_value( $value ) { $value = (string) $value; return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value; }
	private function card( $label, $value ) { echo '<div class="smartforms-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>'; }
	private function top_navigation() {
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'smartforms';
		if ( 'smartforms-form-edit' === $current ) {
			$current = 'smartforms-forms';
		}

		$tabs = array(
			'smartforms'          => array( 'label' => 'Dashboard', 'capability' => 'smartforms_manage_forms', 'icon' => 'dashicons-dashboard' ),
			'smartforms-forms'    => array( 'label' => 'Forms', 'capability' => 'smartforms_manage_forms', 'icon' => 'dashicons-feedback' ),
			'smartforms-entries'  => array( 'label' => 'Entries', 'capability' => 'smartforms_view_entries', 'icon' => 'dashicons-list-view' ),
			'smartforms-sync'     => array( 'label' => 'Sync', 'capability' => 'smartforms_view_delivery_logs', 'icon' => 'dashicons-update' ),
			'smartforms-settings' => array( 'label' => 'Settings', 'capability' => 'smartforms_manage_settings', 'icon' => 'dashicons-admin-generic' ),
		);

		echo '<div class="smartforms-topbar"><a class="smartforms-brand" href="' . esc_url( admin_url( 'admin.php?page=smartforms' ) ) . '"><span class="dashicons dashicons-feedback" aria-hidden="true"></span><strong>SmartForms</strong></a><nav class="smartforms-topnav" aria-label="SmartForms sections">';
		foreach ( $tabs as $slug => $tab ) {
			if ( ! current_user_can( $tab['capability'] ) ) {
				continue;
			}
			$class = 'smartforms-topnav-link' . ( $current === $slug ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $current === $slug ? ' aria-current="page"' : '' ) . '><span class="dashicons ' . esc_attr( $tab['icon'] ) . '" aria-hidden="true"></span>' . esc_html( $tab['label'] ) . '</a>';
		}
		echo '</nav></div>';
	}
	private function guard( $cap ) { if ( ! current_user_can( $cap ) ) { wp_die( 'You do not have permission to access this SmartForms screen.', 403 ); } }
	private function redirect( $path, $notice ) { wp_safe_redirect( add_query_arg( 'smartforms-notice', $notice, admin_url( $path ) ) ); exit; }
	private function notice() { if ( ! empty( $_GET['smartforms-notice'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>SmartForms changes saved.</p></div>'; } }
}
