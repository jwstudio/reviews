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
    $t_contacts = $wpdb->prefix . 'crm_contacts';
    $t_pivot    = $wpdb->prefix . 'crm_company_contact';

    // DELETE
    if (
        isset( $_GET['page'], $_GET['action'], $_GET['id'], $_GET['_wpnonce'] )
        && $_GET['page'] === 'crm-contacts'
        && $_GET['action'] === 'delete'
        && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'crm_delete_contact' )
    ) {
        $id = absint( $_GET['id'] );
        $wpdb->delete( $t_pivot, [ 'contact_id' => $id ], [ '%d' ] );
        $wpdb->delete( $t_contacts, [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=crm-contacts&notice=deleted' ) );
        exit;
    }

    // SAVE (add or edit)
    if (
        isset( $_POST['crm_contact_nonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crm_contact_nonce'] ) ), 'crm_save_contact' )
    ) {
        $data = [
            'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone'      => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'linkedin'   => esc_url_raw( wp_unslash( $_POST['linkedin'] ?? '' ) ),
            'notes'      => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
            'converted'  => isset( $_POST['converted'] ) ? 1 : 0,
            'active'     => isset( $_POST['active'] ) ? 1 : 0,
        ];
        $fmt = [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ];

        $edit_id     = absint( $_POST['edit_id'] ?? 0 );
        $company_ids = array_map( 'absint', (array) ( $_POST['company_ids'] ?? [] ) );

        if ( $edit_id ) {
            $wpdb->update( $t_contacts, $data, [ 'id' => $edit_id ], $fmt, [ '%d' ] );
            $contact_id = $edit_id;
            $wpdb->delete( $t_pivot, [ 'contact_id' => $contact_id ], [ '%d' ] );
            $notice = 'updated';
        } else {
            $wpdb->insert( $t_contacts, $data, $fmt );
            $contact_id = $wpdb->insert_id;
            $notice = 'added';
        }

        foreach ( $company_ids as $cid ) {
            if ( $cid ) {
                $wpdb->replace( $t_pivot, [ 'company_id' => $cid, 'contact_id' => $contact_id ], [ '%d', '%d' ] );
            }
        }

        wp_redirect( admin_url( 'admin.php?page=crm-contacts&notice=' . $notice ) );
        exit;
    }
} );

// ---------------------------------------------------------------------------
// Page display
// ---------------------------------------------------------------------------

function crm_contacts_page() {
    global $wpdb;
    $t_contacts  = $wpdb->prefix . 'crm_contacts';
    $t_companies = $wpdb->prefix . 'crm_companies';
    $t_pivot     = $wpdb->prefix . 'crm_company_contact';

    $notices = [
        'added'   => '<div class="alert alert-success">Contact added.</div>',
        'updated' => '<div class="alert alert-success">Contact updated.</div>',
        'deleted' => '<div class="alert alert-warning">Contact deleted.</div>',
    ];
    $msg = $notices[ sanitize_key( $_GET['notice'] ?? '' ) ] ?? '';

    // LOAD EDIT ROW
    $edit             = null;
    $edit_company_ids = [];
    if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
        $edit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_contacts WHERE id = %d", absint( $_GET['id'] ) ) );
        if ( $edit ) {
            $edit_company_ids = $wpdb->get_col(
                $wpdb->prepare( "SELECT company_id FROM $t_pivot WHERE contact_id = %d", $edit->id )
            );
        }
    }

    $all_companies = $wpdb->get_results( "SELECT id, company_name FROM $t_companies ORDER BY company_name ASC" );

    $contacts = $wpdb->get_results(
        "SELECT c.*, GROUP_CONCAT(co.company_name ORDER BY co.company_name SEPARATOR ', ') AS companies
         FROM $t_contacts c
         LEFT JOIN $t_pivot p  ON p.contact_id  = c.id
         LEFT JOIN $t_companies co ON co.id = p.company_id
         GROUP BY c.id
         ORDER BY c.last_name ASC, c.first_name ASC"
    );

    ?>
    <div class="wrap crm-wrap">
        <h1 class="mb-4">Contacts</h1>
        <?php echo $msg; ?>

        <div class="card mb-4">
            <div class="card-header"><?php echo $edit ? 'Edit Contact' : 'Add Contact'; ?></div>
            <div class="card-body">
                <form method="post">
                    <?php wp_nonce_field( 'crm_save_contact', 'crm_contact_nonce' ); ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit ? esc_attr( $edit->id ) : '0'; ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required
                                value="<?php echo $edit ? esc_attr( $edit->first_name ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required
                                value="<?php echo $edit ? esc_attr( $edit->last_name ) : ''; ?>">
                        </div>
                        <div class="col-md-4">
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
                            <label class="form-label">LinkedIn</label>
                            <input type="url" name="linkedin" class="form-control"
                                value="<?php echo $edit ? esc_attr( $edit->linkedin ) : ''; ?>">
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
                        <div class="col-md-12">
                            <label class="form-label">Companies</label>
                            <select name="company_ids[]" class="form-select" multiple size="4">
                                <?php foreach ( $all_companies as $co ) : ?>
                                    <option value="<?php echo esc_attr( $co->id ); ?>"
                                        <?php echo in_array( $co->id, $edit_company_ids, true ) ? 'selected' : ''; ?>>
                                        <?php echo esc_html( $co->company_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl / Cmd to select multiple companies.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?php echo $edit ? esc_textarea( $edit->notes ) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit ? 'Update Contact' : 'Add Contact'; ?>
                        </button>
                        <?php if ( $edit ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-contacts' ) ); ?>"
                               class="btn btn-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">All Contacts (<?php echo count( $contacts ); ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>LinkedIn</th>
                            <th>Companies</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $contacts ) : foreach ( $contacts as $c ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $c->first_name . ' ' . $c->last_name ); ?></strong></td>
                            <td><?php echo $c->email ? '<a href="mailto:' . esc_attr( $c->email ) . '">' . esc_html( $c->email ) . '</a>' : '—'; ?></td>
                            <td><?php echo $c->phone ? esc_html( $c->phone ) : '—'; ?></td>
                            <td><?php echo $c->linkedin ? '<a href="' . esc_url( $c->linkedin ) . '" target="_blank">View</a>' : '—'; ?></td>
                            <td>
                                <?php if ( $c->companies ) : ?>
                                    <?php foreach ( explode( ', ', $c->companies ) as $name ) : ?>
                                        <span class="badge bg-light text-dark border me-1"><?php echo esc_html( $name ); ?></span>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
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
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-contacts&action=edit&id=' . $c->id ) ); ?>"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=crm-contacts&action=delete&id=' . $c->id ), 'crm_delete_contact' ) ); ?>"
                                   class="btn btn-sm btn-outline-danger ms-1"
                                   onclick="return confirm('Delete this contact?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7" class="text-muted text-center py-4">No contacts yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
