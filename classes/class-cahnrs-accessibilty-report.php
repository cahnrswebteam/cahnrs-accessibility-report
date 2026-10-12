<?php namespace CAHNRS\Plugin\AccessibilityReport;

class CAHNRS_Accessibility_Report_Menu {

    private $total_errors   = 0;
    private $total_alerts   = 0;
    private $total_warnings = 0;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'cahnrs_accessibility_menu_page' ) );
    }

    public function cahnrs_accessibility_menu_page() {
        $parent_slug = 'cahnrs-accessibility';

        add_menu_page(
            'WSU Accessibility Report',
            'WSU Accessibility Report',
            'manage_options',
            $parent_slug,
            array( $this, 'cahnrs_create_menu_page' ),
            'dashicons-admin-generic'
        );

        add_submenu_page(
            $parent_slug,
            'WSU Accessibility Report Settings',
            'Settings',
            'manage_options',
            'cahnrs-settings-page',
            array( $this, 'cahnrs_create_sub_page' )
        );
    }

    public function cahnrs_create_menu_page() {
        $this->total_errors   = 0;
        $this->total_alerts   = 0;
        $this->total_warnings = 0;

        echo '<div style="display:flex;align-items: baseline;">';
        echo '<div class="wrap" style="flex: 1 65%;">';
        echo '<h2>' . esc_html( get_bloginfo( 'name' ) ) . ' Accessibility Report</h2>';

        $selected_post_types = array( 'post', 'page' );

        $selected_issue_types = get_option(
            'selected_issue_types',
            array( 'errors', 'alerts', 'warnings' )
        );

        if ( empty( $selected_issue_types ) || ! is_array( $selected_issue_types ) ) {
            $selected_issue_types = array( 'errors', 'alerts', 'warnings' );
        }

        foreach ( $selected_post_types as $selected_post_type ) {
            $this->cahnrs_generate_post_type_report( $selected_post_type, $selected_issue_types );
        }

        echo '</div>';

        $this->cahnrs_generate_summary_sidebar();

        echo '</div>';
    }

    public function cahnrs_generate_post_type_report( $selected_post_type, $selected_issue_types ) {
        $args = array(
            'post_type'      => $selected_post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        $query = new \WP_Query( $args );

        if ( empty( $query->posts ) ) {
            wp_reset_postdata();
            return;
        }

        echo '<h3>' . esc_html( ucfirst( $selected_post_type ) ) . 's</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>Accessibility Issues</th></tr></thead>';
        echo '<tbody>';

        $rows_found = false;

        foreach ( $query->posts as $post_id ) {
            $report = $this->get_accessibility_report( $post_id );

            if ( ! $report ) {
                continue;
            }

            $has_selected_issues = false;

            foreach ( $selected_issue_types as $issue_type ) {
                $issues = $this->get_accessibility_issue_items( $report, $issue_type );

                if ( ! empty( $issues ) ) {
                    $has_selected_issues = true;
                    break;
                }
            }

            if ( ! $has_selected_issues ) {
                continue;
            }

            $rows_found = true;

            echo '<tr>';

            echo '<td>';
            echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">';
            echo esc_html( get_the_title( $post_id ) );
            echo '</a>';
            echo '</td>';

            echo '<td>';

            $errors = $this->get_accessibility_issue_items( $report, 'errors' );

            if ( in_array( 'errors', $selected_issue_types, true ) && ! empty( $errors ) ) {
                echo "<h4 style='margin: 0;'>Errors</h4>";

                foreach ( $errors as $error ) {
                    echo esc_html( $this->get_issue_message( $error ) ) . ' (Error)<br>';
                    $this->total_errors++;
                }
            }

            $alerts = $this->get_accessibility_issue_items( $report, 'alerts' );

            if ( in_array( 'alerts', $selected_issue_types, true ) && ! empty( $alerts ) ) {
                echo "<h4 style='margin: 0;'>Alerts</h4>";

                foreach ( $alerts as $alert ) {
                    echo esc_html( $this->get_issue_message( $alert ) ) . ' (Alert)<br>';
                    $this->total_alerts++;
                }
            }

            $warnings = $this->get_accessibility_issue_items( $report, 'warnings' );

            if ( in_array( 'warnings', $selected_issue_types, true ) && ! empty( $warnings ) ) {
                echo "<h4 style='margin: 0;'>Warnings</h4>";

                foreach ( $warnings as $warning ) {
                    echo esc_html( $this->get_issue_message( $warning ) ) . ' (Warning)<br>';
                    $this->total_warnings++;
                }
            }

            echo '</td>';
            echo '</tr>';
        }

        if ( ! $rows_found ) {
            echo '<tr>';
            echo '<td colspan="2">';
            echo esc_html__( 'No accessibility issues found for this post type.', 'wsuwp' );
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        wp_reset_postdata();
    }

    private function cahnrs_generate_summary_sidebar() {
        $selected_issue_types = get_option(
            'selected_issue_types',
            array( 'errors', 'alerts', 'warnings' )
        );

        if ( empty( $selected_issue_types ) || ! is_array( $selected_issue_types ) ) {
            $selected_issue_types = array( 'errors', 'alerts', 'warnings' );
        }

        echo '<div style="flex: 1 15%;background: white;padding: 20px 20px 110px;border-top: #ca1237 6px solid;position: relative;top: 120px;">';

        echo '<h3>Total accessibility issues</h3>';

        if (
            ( in_array( 'errors', $selected_issue_types, true ) && $this->total_errors > 0 ) ||
            ( in_array( 'alerts', $selected_issue_types, true ) && $this->total_alerts > 0 ) ||
            ( in_array( 'warnings', $selected_issue_types, true ) && $this->total_warnings > 0 )
        ) {
            echo '<p>To change the accessibility issues that show up, please go to the ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=cahnrs-settings-page' ) ) . '">settings page</a>.';
            echo '</p>';

            if ( in_array( 'errors', $selected_issue_types, true ) ) {
                echo '<p style="margin:0;"><span style="font-weight:bold;font-size: 21px;">' . esc_html( $this->total_errors ) . '</span> Errors</p>';
            }

            if ( in_array( 'alerts', $selected_issue_types, true ) ) {
                echo '<p style="margin:0;"><span style="font-weight:bold;font-size: 21px;">' . esc_html( $this->total_alerts ) . '</span> Alerts</p>';
            }

            if ( in_array( 'warnings', $selected_issue_types, true ) ) {
                echo '<p style="margin:0;"><span style="font-weight:bold;font-size: 21px;">' . esc_html( $this->total_warnings ) . '</span> Warnings</p>';
            }
        } else {
            echo '<p>There were no accessibility issues found on your site.</p>';
        }

        echo '</div>';
    }

    public function cahnrs_create_sub_page() {
        $selected_issue_types = get_option(
            'selected_issue_types',
            array( 'errors', 'alerts', 'warnings' )
        );

        $report_email    = get_option( 'report_email', '' );
        $email_frequency = get_option( 'email_frequency', 'monthly' );

        if ( isset( $_POST['submit'] ) ) {
            $this->cahnrs_process_settings_form();

            $selected_issue_types = get_option(
                'selected_issue_types',
                array( 'errors', 'alerts', 'warnings' )
            );

            $report_email    = get_option( 'report_email', '' );
            $email_frequency = get_option( 'email_frequency', 'monthly' );
        }

        $this->displaySettingsForm( $selected_issue_types, $report_email, $email_frequency );
    }

    private function cahnrs_process_settings_form() {
        $email_frequency = get_option( 'email_frequency', 'monthly' );

        $allowed_issue_types = array( 'errors', 'alerts', 'warnings' );

        $selected_issue_types = isset( $_POST['issue_types'] ) && is_array( $_POST['issue_types'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['issue_types'] ) )
            : array();

        $selected_issue_types = array_values(
            array_intersect( $selected_issue_types, $allowed_issue_types )
        );

        update_option( 'selected_issue_types', $selected_issue_types );

        $report_email = isset( $_POST['report_email'] )
            ? sanitize_email( wp_unslash( $_POST['report_email'] ) )
            : '';

        update_option( 'report_email', $report_email );

        $selected_recipients = isset( $_POST['selected_recipients'] ) && is_array( $_POST['selected_recipients'] )
            ? array_map( 'sanitize_email', wp_unslash( $_POST['selected_recipients'] ) )
            : array();

        update_option( 'selected_recipients', $selected_recipients );

        $allowed_tags = array(
            'a'  => array(
                'href'  => array(),
                'title' => array(),
            ),
            'br' => array(),
            'em' => array(),
            'ul' => array(),
            'li' => array(),
            'p'  => array(),
        );

        $custom_email_content = isset( $_POST['custom_email_content'] )
            ? wp_unslash( $_POST['custom_email_content'] )
            : '';

        $clean_email_content = wp_kses( $custom_email_content, $allowed_tags );

        update_option( 'custom_email_content', $clean_email_content );

        $allowed_frequencies = array( 'none', 'daily', 'weekly', 'monthly' );

        $new_email_frequency = isset( $_POST['email_frequency'] )
            ? sanitize_text_field( wp_unslash( $_POST['email_frequency'] ) )
            : 'monthly';

        if ( ! in_array( $new_email_frequency, $allowed_frequencies, true ) ) {
            $new_email_frequency = 'monthly';
        }

        if ( $email_frequency !== $new_email_frequency ) {
            update_option( 'email_frequency', $new_email_frequency );

            include_once 'class-cahnrs-accessibility-email-cron.php';

            $cahnrs_wsu_set_email_cron = new CAHNRSEmailCron();
            $cahnrs_wsu_set_email_cron->cahnrs_accessibility_set_frequency();
        }

        echo '<div class="updated notice"><p>Settings successfully saved!</p></div>';
    }

    private function displaySettingsForm( $selected_issue_types, $selected_recipients, $email_frequency ) {
        $selected_issue_types = get_option(
            'selected_issue_types',
            array( 'errors', 'alerts', 'warnings' )
        );

        if ( empty( $selected_issue_types ) || ! is_array( $selected_issue_types ) ) {
            $selected_issue_types = array( 'errors', 'alerts', 'warnings' );
        }

        $report_email         = get_option( 'report_email', '' );
        $email_frequency      = get_option( 'email_frequency', 'none' );
        $custom_email_content = get_option( 'custom_email_content', '' );

        $default_email_content = CAHNRSEmailCronSchedule::cahnrs_generate_report_content();
        $cahnrs_site_title     = get_bloginfo( 'name' );
        $cahnrs_admin_report_page = admin_url( 'admin.php?page=cahnrs-accessibility' );

        $issue_types = array(
            'errors'   => 'Errors',
            'alerts'   => 'Alerts',
            'warnings' => 'Warnings',
        );

        $user_query = new \WP_User_Query( array(
            'role__in' => array( 'administrator', 'editor' ),
        ) );

        $users = $user_query->get_results();

        $selected_recipients = get_option( 'selected_recipients', array() );

        include CAHNRSAccessibilityReportPlugin::get( 'dir' ) . '/assets/templates/wsuwp-accessibility-settings-form.php';
    }

    private function get_accessibility_report( $post_id ) {
        $report_data = get_post_meta( $post_id, '_wsuwp_accessibility_report', true );

        if ( empty( $report_data ) ) {
            $report_data = get_post_meta( $post_id, 'wsuwp_accessibility_report', true );
        }

        if ( empty( $report_data ) ) {
            return false;
        }

        if ( is_string( $report_data ) ) {
            $decoded = json_decode( $report_data );

            if ( json_last_error() === JSON_ERROR_NONE && is_object( $decoded ) ) {
                return $decoded;
            }

            return false;
        }

        if ( is_object( $report_data ) ) {
            return $report_data;
        }

        if ( is_array( $report_data ) ) {
            $decoded = json_decode( wp_json_encode( $report_data ) );

            if ( json_last_error() === JSON_ERROR_NONE && is_object( $decoded ) ) {
                return $decoded;
            }
        }

        return false;
    }

    private function get_accessibility_issue_items( $report, $issue_type ) {
        if ( ! is_object( $report ) || empty( $report->{$issue_type} ) ) {
            return array();
        }

        $issues = $report->{$issue_type};

        if ( is_array( $issues ) ) {
            return $issues;
        }

        if ( is_object( $issues ) && ! empty( $issues->items ) && is_array( $issues->items ) ) {
            return $issues->items;
        }

        return array();
    }

    private function get_issue_message( $issue ) {
        if ( is_object( $issue ) && ! empty( $issue->message ) ) {
            return $issue->message;
        }

        if ( is_array( $issue ) && ! empty( $issue['message'] ) ) {
            return $issue['message'];
        }

        if ( is_string( $issue ) ) {
            return $issue;
        }

        return 'Accessibility issue found';
    }
}

$CAHNRS_Accessibility_Report_Menu = new CAHNRS_Accessibility_Report_Menu();