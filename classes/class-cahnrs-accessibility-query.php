<?php namespace CAHNRS\Plugin\AccessibilityReport;

class CAHNRSAccessibilityQuery {

    private $total_errors   = 0;
    private $total_alerts   = 0;
    private $total_warnings = 0;

    public function cahnrs_report_query() {
        $this->total_errors   = 0;
        $this->total_alerts   = 0;
        $this->total_warnings = 0;

        $selected_post_types = array( 'post', 'page' );

        $selected_issue_types = get_option(
            'selected_issue_types',
            array( 'errors', 'alerts', 'warnings' )
        );

        if ( empty( $selected_issue_types ) || ! is_array( $selected_issue_types ) ) {
            $selected_issue_types = array( 'errors', 'alerts', 'warnings' );
        }

        $args = array(
            'post_type'      => $selected_post_types,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        $query = new \WP_Query( $args );

        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $post_id ) {
                $report = $this->get_accessibility_report( $post_id );

                if ( ! $report ) {
                    continue;
                }

                if ( in_array( 'errors', $selected_issue_types, true ) ) {
                    $errors = $this->get_accessibility_issue_items( $report, 'errors' );
                    $this->total_errors += count( $errors );
                }

                if ( in_array( 'alerts', $selected_issue_types, true ) ) {
                    $alerts = $this->get_accessibility_issue_items( $report, 'alerts' );
                    $this->total_alerts += count( $alerts );
                }

                if ( in_array( 'warnings', $selected_issue_types, true ) ) {
                    $warnings = $this->get_accessibility_issue_items( $report, 'warnings' );
                    $this->total_warnings += count( $warnings );
                }
            }
        }

        wp_reset_postdata();

        return array(
            'total_errors'   => $this->total_errors,
            'total_warnings' => $this->total_warnings,
            'total_alerts'   => $this->total_alerts,
        );
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
}