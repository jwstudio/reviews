<?php
defined( 'ABSPATH' ) || exit;

function crm_company_view_page( $id ) {
    global $wpdb;
    $table       = $wpdb->prefix . 'crm_companies';
    $t_contacts  = $wpdb->prefix . 'crm_contacts';
    $t_pivot     = $wpdb->prefix . 'crm_company_contact';

    $company = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

    if ( ! $company ) {
        echo '<div class="wrap crm-wrap"><div class="alert alert-danger">Company not found.</div></div>';
        return;
    }

    $contacts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT c.* FROM $t_contacts c
             INNER JOIN $t_pivot p ON p.contact_id = c.id
             WHERE p.company_id = %d
             ORDER BY c.last_name ASC, c.first_name ASC",
            $id
        )
    );

    $back_url = admin_url( 'admin.php?page=crm-companies' );
    $edit_url = admin_url( 'admin.php?page=crm-companies&action=edit&id=' . $id );

    ?>
    <div class="wrap crm-wrap">

        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-sm btn-outline-secondary">&larr; Companies</a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-outline-primary ms-auto">Edit</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center g-4">

                    <!-- Logo placeholder -->
                    <div class="col-auto">
                        <div class="crm-logo-placeholder d-flex align-items-center justify-content-center bg-light border rounded text-muted"
                             style="width:100px;height:100px;font-size:.75rem;">Logo</div>
                    </div>

                    <!-- Company details -->
                    <div class="col">
                        <h2 class="mb-1"><?php echo esc_html( $company->company_name ); ?></h2>
                        <div class="mb-2">
                            <?php if ( $company->converted ) : ?>
                                <span class="badge bg-success">Client</span>
                            <?php else : ?>
                                <span class="badge bg-secondary">Enquiry</span>
                            <?php endif; ?>
                            <?php if ( $company->active ) : ?>
                                <span class="badge bg-primary ms-1">Active</span>
                            <?php else : ?>
                                <span class="badge bg-light text-muted border ms-1">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <dl class="row mb-0" style="max-width:600px">
                            <?php if ( $company->email ) : ?>
                                <dt class="col-sm-3">Email</dt>
                                <dd class="col-sm-9"><a href="mailto:<?php echo esc_attr( $company->email ); ?>"><?php echo esc_html( $company->email ); ?></a></dd>
                            <?php endif; ?>
                            <?php if ( $company->phone ) : ?>
                                <dt class="col-sm-3">Phone</dt>
                                <dd class="col-sm-9"><?php echo esc_html( $company->phone ); ?></dd>
                            <?php endif; ?>
                            <?php foreach ( [ 'website' => 'Website', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram' ] as $field => $label ) : ?>
                                <?php if ( $company->$field ) : ?>
                                    <dt class="col-sm-3"><?php echo $label; ?></dt>
                                    <dd class="col-sm-9"><a href="<?php echo esc_url( $company->$field ); ?>" target="_blank"><?php echo esc_html( $company->$field ); ?></a></dd>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned contacts -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Contacts (<?php echo count( $contacts ); ?>)</span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=crm-contacts' ) ); ?>"
                   class="btn btn-sm btn-outline-primary">Add Contact</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>LinkedIn</th>
                            <th>Status</th>
                            <th></th>
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
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No contacts assigned to this company.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}
