<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Form & delete handlers — run in admin_init so wp_redirect works before output
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_companies';
    $pivot = $wpdb->prefix . 'crm_company_contact';

    // DELETE
    if (
        isset( $_GET['page'], $_GET['action'], $_GET['id'], $_GET['_wpnonce'] )
        && $_GET['page'] === 'crm-companies'
        && $_GET['action'] === 'delete'
        && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'crm_delete_company' )
    ) {
        $id = absint( $_GET['id'] );
        $wpdb->delete( $pivot, [ 'company_id' => $id ], [ '%d' ] );
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=crm-companies&notice=deleted' ) );
        exit;
    }

    // SAVE (add or edit)
    if (
        isset( $_POST['crm_company_nonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crm_company_nonce'] ) ), 'crm_save_company' )
    ) {
        $data = [
            'company_name' => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
            'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone'        => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'website'      => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
            'linkedin'     => esc_url_raw( wp_unslash( $_POST['linkedin'] ?? '' ) ),
            'facebook'     => esc_url_raw( wp_unslash( $_POST['facebook'] ?? '' ) ),
            'instagram'    => esc_url_raw( wp_unslash( $_POST['instagram'] ?? '' ) ),
            'converted'    => isset( $_POST['converted'] ) ? 1 : 0,
            'active'       => isset( $_POST['active'] ) ? 1 : 0,
        ];
        $fmt = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ];

        $edit_id = absint( $_POST['edit_id'] ?? 0 );
        if ( $edit_id ) {
            $wpdb->update( $table, $data, [ 'id' => $edit_id ], $fmt, [ '%d' ] );
            wp_redirect( admin_url( 'admin.php?page=crm-companies&notice=updated' ) );
        } else {
            $wpdb->insert( $table, $data, $fmt );
            wp_redirect( admin_url( 'admin.php?page=crm-companies&notice=added' ) );
        }
        exit;
    }
} );

// ---------------------------------------------------------------------------
// Page display
// ---------------------------------------------------------------------------

function crm_companies_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'crm_companies';

    // Flash notices from redirect
    $notices = [
        'added'   => '<div class="alert alert-success">Company added.</div>',
        'updated' => '<div class="alert alert-success">Company updated.</div>',
        'deleted' => '<div class="alert alert-warning">Company deleted.</div>',
    ];
    $msg = $notices[ sanitize_key( $_GET['notice'] ?? '' ) ] ?? '';

    // LOAD EDIT ROW
    $edit = null;
    if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
        $edit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $_GET['id'] ) ) );
    }

    // VIEW — delegate to dedicated display function
    if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'view' ) {
        crm_company_view_page( absint( $_GET['id'] ) );
        return;
    }

    $companies = $wpdb->get_results( "SELECT * FROM $table ORDER BY company_name ASC" );

    ?>
    <div class="wrap crm-wrap">
        <h1 class="mb-4">Companies</h1>
        <?php echo $msg; ?>

        <div class="card mb-4">
            <div class="card-header"><?php echo $edit ? 'Edit Company' : 'Add Company'; ?></div>
            <div class="card-body">
                <form method="post">
                    <?php wp_nonce_field( 'crm_save_company', 'crm_company_nonce' ); ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit ? esc_attr( $edit->id ) : '0'; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required
                                value="<?php echo $edit ? esc_attr( $edit->company_name ) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->email ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->phone ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->website ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">LinkedIn</label>
                            <input type="url" name="linkedin" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->linkedin ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Facebook</label>
                            <input type="url" name="facebook" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->facebook ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Instagram</label>
                            <input type="url" name="instagram" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->instagram ) : ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">Converted</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="converted" id="converted"
                                    <?php echo ( ! $edit || $edit->converted ) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="converted">Client</label>
                            </div>
                            <div class="form-text">Off = enquiry only</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="active" id="active"
                                    <?php echo ( ! $edit || $edit->active ) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">Active</label>
                            </div>
                            <div class="form-text">Off = inactive</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit ? 'Update Company' : 'Add Company'; ?>
                        </button>
                        <?php if ( $edit ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-companies' ) ); ?>"
                               class="btn btn-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">All Companies (<?php echo count( $companies ); ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Links</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $companies ) : foreach ( $companies as $c ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $c->company_name ); ?></strong></td>
                            <td><?php echo $c->email ? '<a href="mailto:' . esc_attr( $c->email ) . '">' . esc_html( $c->email ) . '</a>' : '—'; ?></td>
                            <td><?php echo $c->phone ? esc_html( $c->phone ) : '—'; ?></td>
                            <td>
                                <?php foreach ( [ 'website' => 'W', 'linkedin' => 'Li', 'facebook' => 'Fb', 'instagram' => 'Ig' ] as $field => $label ) : ?>
                                    <?php if ( $c->$field ) : ?>
                                        <a href="<?php echo esc_url( $c->$field ); ?>" target="_blank"
                                           class="badge bg-secondary text-decoration-none me-1"><?php echo $label; ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php if ( $c->converted ) : ?>
                                    <span class="badge bg-success">Client</span>
                                <?php else : ?>
                                    <span class="badge bg-secondary">Enquiry</span>
                                <?php endif; ?>
                                <?php if ( $c->active ) : ?>
                                    <span class="badge bg-primary ms-1">Active</span>
                                <?php else : ?>
                                    <span class="badge bg-light text-muted border ms-1">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-companies&action=view&id=' . $c->id ) ); ?>"
                                   class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-companies&action=edit&id=' . $c->id ) ); ?>"
                                   class="btn btn-sm btn-outline-primary ms-1">Edit</a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=crm-companies&action=delete&id=' . $c->id ), 'crm_delete_company' ) ); ?>"
                                   class="btn btn-sm btn-outline-danger ms-1"
                                   onclick="return confirm('Delete this company and all its contact links?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No companies yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
