<?php
defined( 'ABSPATH' ) || exit;

function crm_dashboard_page() {
    global $wpdb;
    $company_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_companies" );
    $contact_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_contacts" );

    $recent_companies = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}crm_companies ORDER BY created_at DESC LIMIT 5"
    );
    $recent_contacts = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}crm_contacts ORDER BY created_at DESC LIMIT 5"
    );
    ?>
    <div class="wrap crm-wrap">
        <h1 class="mb-4">CRM Dashboard</h1>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <h2 class="display-5 fw-bold"><?php echo (int) $company_count; ?></h2>
                        <p class="text-muted mb-0">Companies</p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-companies' ) ); ?>"
                           class="btn btn-sm btn-outline-primary mt-2">View All</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <h2 class="display-5 fw-bold"><?php echo (int) $contact_count; ?></h2>
                        <p class="text-muted mb-0">Contacts</p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-contacts' ) ); ?>"
                           class="btn btn-sm btn-outline-primary mt-2">View All</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Recent Companies</div>
                    <ul class="list-group list-group-flush">
                        <?php if ( $recent_companies ) : foreach ( $recent_companies as $c ) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo esc_html( $c->company_name ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-companies&action=edit&id=' . $c->id ) ); ?>"
                                   class="btn btn-sm btn-link p-0">Edit</a>
                            </li>
                        <?php endforeach; else : ?>
                            <li class="list-group-item text-muted">No companies yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Recent Contacts</div>
                    <ul class="list-group list-group-flush">
                        <?php if ( $recent_contacts ) : foreach ( $recent_contacts as $c ) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo esc_html( $c->first_name . ' ' . $c->last_name ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-contacts&action=edit&id=' . $c->id ) ); ?>"
                                   class="btn btn-sm btn-link p-0">Edit</a>
                            </li>
                        <?php endforeach; else : ?>
                            <li class="list-group-item text-muted">No contacts yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}
