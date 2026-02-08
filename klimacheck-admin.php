<?php
/**
 * Plugin Name: KlimaCheck Wolfratshausen
 * Description: Admin page to manage Climate Check candidate responses for Wolfratshausen.
 * Version: 1.0.0
 * Author: KlimaCheck Initiative
 * Text Domain: klimacheck
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KLIMACHECK_OPTION_KEY', 'wolfratshausen_klima_data' );
define( 'KLIMACHECK_NONCE_ACTION', 'klimacheck_save' );

/**
 * Return the 10 default questions. Admins can override the question text
 * and the "Why is this important?" explanation via the settings page.
 */
function klimacheck_default_questions() {
    return array(
        1  => array(
            'question' => 'Frage 1',
            'why'      => '',
        ),
        2  => array(
            'question' => 'Frage 2',
            'why'      => '',
        ),
        3  => array(
            'question' => 'Frage 3',
            'why'      => '',
        ),
        4  => array(
            'question' => 'Frage 4',
            'why'      => '',
        ),
        5  => array(
            'question' => 'Frage 5',
            'why'      => '',
        ),
        6  => array(
            'question' => 'Frage 6',
            'why'      => '',
        ),
        7  => array(
            'question' => 'Frage 7',
            'why'      => '',
        ),
        8  => array(
            'question' => 'Frage 8',
            'why'      => '',
        ),
        9  => array(
            'question' => 'Frage 9',
            'why'      => '',
        ),
        10 => array(
            'question' => 'Frage 10',
            'why'      => '',
        ),
    );
}

/** Load persisted data (questions + candidates). */
function klimacheck_get_data() {
    $data = get_option( KLIMACHECK_OPTION_KEY, null );
    if ( ! is_array( $data ) ) {
        $data = array(
            'questions'  => klimacheck_default_questions(),
            'candidates' => array(),
        );
    }
    if ( empty( $data['questions'] ) ) {
        $data['questions'] = klimacheck_default_questions();
    }
    return $data;
}

/** Persist data. */
function klimacheck_save_data( $data ) {
    update_option( KLIMACHECK_OPTION_KEY, $data, false );
}

/** Generate a unique token for candidate review links. */
function klimacheck_generate_token() {
    return bin2hex( random_bytes( 16 ) );
}

/* ------------------------------------------------------------------ */
/*  Admin menu                                                         */
/* ------------------------------------------------------------------ */
add_action( 'admin_menu', 'klimacheck_register_menu' );

function klimacheck_register_menu() {
    add_menu_page(
        'KlimaCheck',
        'KlimaCheck',
        'manage_options',
        'klimacheck',
        'klimacheck_page_overview',
        'dashicons-clipboard',
        80
    );

    add_submenu_page(
        'klimacheck',
        'Overview',
        'Overview',
        'manage_options',
        'klimacheck',
        'klimacheck_page_overview'
    );

    add_submenu_page(
        'klimacheck',
        'Questions',
        'Questions',
        'manage_options',
        'klimacheck-questions',
        'klimacheck_page_questions'
    );

    add_submenu_page(
        'klimacheck',
        'Candidate',
        'Add / Edit Candidate',
        'manage_options',
        'klimacheck-candidate',
        'klimacheck_page_candidate'
    );

    // Hidden page for candidate review (accessible via token link).
    add_submenu_page(
        null, // hidden
        'Candidate Review',
        'Candidate Review',
        'manage_options',
        'klimacheck-review',
        'klimacheck_page_review_admin'
    );
}

/* ------------------------------------------------------------------ */
/*  Admin styles                                                       */
/* ------------------------------------------------------------------ */
add_action( 'admin_head', 'klimacheck_admin_styles' );

function klimacheck_admin_styles() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'klimacheck' ) === false ) {
        return;
    }
    ?>
    <style>
        .klimacheck-wrap { max-width: 1100px; }
        .klimacheck-wrap h1 { margin-bottom: 16px; }
        .klimacheck-table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        .klimacheck-table th,
        .klimacheck-table td { border: 1px solid #ccd0d4; padding: 8px 10px; text-align: left; vertical-align: top; }
        .klimacheck-table th { background: #f0f0f1; }
        .klimacheck-status-yes { color: #00a32a; font-weight: 600; }
        .klimacheck-status-partially { color: #dba617; font-weight: 600; }
        .klimacheck-status-no { color: #d63638; font-weight: 600; }
        .klimacheck-status-missing { color: #999; }
        .klimacheck-question-block { background: #f9f9f9; border: 1px solid #ddd; padding: 14px 18px; margin-bottom: 18px; border-radius: 4px; }
        .klimacheck-question-block h3 { margin-top: 0; }
        .klimacheck-radio-group label { display: inline-block; margin-right: 18px; }
        .klimacheck-char-count { font-size: 12px; color: #666; margin-top: 2px; }
        .klimacheck-error { color: #d63638; font-weight: 600; }
        .klimacheck-actions { display: flex; gap: 6px; }
        .klimacheck-actions a,
        .klimacheck-actions button { white-space: nowrap; }
        .klimacheck-review-link { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
        .klimacheck-review-link input[type="text"] { width: 420px; font-size: 12px; }
    </style>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Page: Overview (table of candidates & question status)             */
/* ------------------------------------------------------------------ */
function klimacheck_page_overview() {
    $data = klimacheck_get_data();

    // Handle delete action.
    if ( isset( $_GET['action'], $_GET['candidate_id'], $_GET['_wpnonce'] )
         && $_GET['action'] === 'delete'
         && wp_verify_nonce( $_GET['_wpnonce'], 'klimacheck_delete_' . $_GET['candidate_id'] )
    ) {
        $cid = sanitize_text_field( $_GET['candidate_id'] );
        if ( isset( $data['candidates'][ $cid ] ) ) {
            unset( $data['candidates'][ $cid ] );
            klimacheck_save_data( $data );
            $data = klimacheck_get_data();
            echo '<div class="notice notice-success"><p>Candidate deleted.</p></div>';
        }
    }

    // Handle token regeneration.
    if ( isset( $_GET['action'], $_GET['candidate_id'], $_GET['_wpnonce'] )
         && $_GET['action'] === 'regenerate_token'
         && wp_verify_nonce( $_GET['_wpnonce'], 'klimacheck_regen_' . $_GET['candidate_id'] )
    ) {
        $cid = sanitize_text_field( $_GET['candidate_id'] );
        if ( isset( $data['candidates'][ $cid ] ) ) {
            $data['candidates'][ $cid ]['token'] = klimacheck_generate_token();
            klimacheck_save_data( $data );
            $data = klimacheck_get_data();
            echo '<div class="notice notice-success"><p>Review link regenerated.</p></div>';
        }
    }

    $questions  = $data['questions'];
    $candidates = $data['candidates'];

    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; Candidate Overview</h1>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-candidate' ) ); ?>" class="button button-primary">+ Add Candidate</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-questions' ) ); ?>" class="button">Manage Questions</a>
        </p>

        <?php if ( empty( $candidates ) ) : ?>
            <p>No candidates added yet.</p>
        <?php else : ?>
            <table class="klimacheck-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Party</th>
                        <?php for ( $q = 1; $q <= 10; $q++ ) : ?>
                            <th title="<?php echo esc_attr( $questions[ $q ]['question'] ); ?>">Q<?php echo $q; ?></th>
                        <?php endfor; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $candidates as $cid => $c ) : ?>
                        <tr>
                            <td><?php echo esc_html( $c['name'] ); ?></td>
                            <td><?php echo esc_html( $c['party'] ); ?></td>
                            <?php for ( $q = 1; $q <= 10; $q++ ) :
                                $answer = isset( $c['responses'][ $q ]['answer'] ) ? $c['responses'][ $q ]['answer'] : '';
                                $text   = isset( $c['responses'][ $q ]['text'] ) ? $c['responses'][ $q ]['text'] : '';
                                if ( $answer && $text ) {
                                    if ( $answer === 'yes' ) {
                                        $cls   = 'klimacheck-status-yes';
                                        $label = 'Yes';
                                    } elseif ( $answer === 'partially' ) {
                                        $cls   = 'klimacheck-status-partially';
                                        $label = 'Partially';
                                    } else {
                                        $cls   = 'klimacheck-status-no';
                                        $label = 'No';
                                    }
                                    echo '<td class="' . $cls . '" title="' . esc_attr( $text ) . '">' . $label . '</td>';
                                } else {
                                    echo '<td class="klimacheck-status-missing">&mdash;</td>';
                                }
                            endfor; ?>
                            <td>
                                <div class="klimacheck-actions">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-candidate&candidate_id=' . $cid ) ); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-review&candidate_id=' . $cid ) ); ?>" class="button button-small">Preview</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=klimacheck&action=delete&candidate_id=' . $cid ), 'klimacheck_delete_' . $cid ) ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('Delete this candidate?');">Delete</a>
                                </div>
                                <?php
                                $token     = isset( $c['token'] ) ? $c['token'] : '';
                                $review_url = $token ? add_query_arg(
                                    array( 'klimacheck_review' => $token ),
                                    home_url( '/' )
                                ) : '';
                                ?>
                                <?php if ( $review_url ) : ?>
                                    <div class="klimacheck-review-link">
                                        <input type="text" readonly value="<?php echo esc_attr( $review_url ); ?>" onclick="this.select();" />
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=klimacheck&action=regenerate_token&candidate_id=' . $cid ), 'klimacheck_regen_' . $cid ) ); ?>" class="button button-small" title="Generate new link">&#x21bb;</a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Hover over a status cell to see the candidate's statement. The review link below each candidate can be sent to them &mdash; it only shows <strong>their own</strong> answers.</p>
        <?php endif; ?>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Page: Manage Questions                                             */
/* ------------------------------------------------------------------ */
function klimacheck_page_questions() {
    $data = klimacheck_get_data();

    // Save handler.
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['klimacheck_questions_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['klimacheck_questions_nonce'], 'klimacheck_save_questions' ) ) {
            wp_die( 'Security check failed.' );
        }

        for ( $q = 1; $q <= 10; $q++ ) {
            $data['questions'][ $q ]['question'] = sanitize_text_field( $_POST['question_' . $q] ?? '' );
            $data['questions'][ $q ]['why']      = sanitize_textarea_field( $_POST['why_' . $q] ?? '' );
        }
        klimacheck_save_data( $data );
        echo '<div class="notice notice-success"><p>Questions saved.</p></div>';
    }

    $questions = $data['questions'];
    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; Manage Questions</h1>
        <form method="post">
            <?php wp_nonce_field( 'klimacheck_save_questions', 'klimacheck_questions_nonce' ); ?>
            <table class="klimacheck-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Question</th>
                        <th>Why is this important?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ( $q = 1; $q <= 10; $q++ ) : ?>
                        <tr>
                            <td><?php echo $q; ?></td>
                            <td>
                                <input type="text" name="question_<?php echo $q; ?>" value="<?php echo esc_attr( $questions[ $q ]['question'] ); ?>" class="regular-text" style="width:100%;" />
                            </td>
                            <td>
                                <textarea name="why_<?php echo $q; ?>" rows="3" style="width:100%;"><?php echo esc_textarea( $questions[ $q ]['why'] ); ?></textarea>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <p><button type="submit" class="button button-primary">Save Questions</button></p>
        </form>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Page: Add / Edit Candidate                                         */
/* ------------------------------------------------------------------ */
function klimacheck_page_candidate() {
    $data        = klimacheck_get_data();
    $questions   = $data['questions'];
    $candidate_id = isset( $_GET['candidate_id'] ) ? sanitize_text_field( $_GET['candidate_id'] ) : '';
    $is_edit     = $candidate_id && isset( $data['candidates'][ $candidate_id ] );
    $candidate   = $is_edit ? $data['candidates'][ $candidate_id ] : array(
        'name'      => '',
        'party'     => '',
        'responses' => array(),
        'token'     => klimacheck_generate_token(),
    );

    $errors = array();

    // Save handler.
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['klimacheck_candidate_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['klimacheck_candidate_nonce'], 'klimacheck_save_candidate' ) ) {
            wp_die( 'Security check failed.' );
        }

        $candidate['name']  = sanitize_text_field( $_POST['candidate_name'] ?? '' );
        $candidate['party'] = sanitize_text_field( $_POST['candidate_party'] ?? '' );

        if ( empty( $candidate['name'] ) ) {
            $errors[] = 'Candidate name is required.';
        }

        for ( $q = 1; $q <= 10; $q++ ) {
            $answer = sanitize_text_field( $_POST[ 'answer_' . $q ] ?? '' );
            $text   = sanitize_textarea_field( $_POST[ 'text_' . $q ] ?? '' );

            if ( strlen( $text ) > 500 ) {
                $text = mb_substr( $text, 0, 500 );
            }

            if ( $answer && ! $text ) {
                $errors[] = 'Question ' . $q . ': A statement is required when a position is selected ("because…").';
            }

            $candidate['responses'][ $q ] = array(
                'answer' => $answer,
                'text'   => $text,
            );
        }

        if ( empty( $errors ) ) {
            if ( ! $is_edit ) {
                $candidate_id = 'c_' . time() . '_' . wp_rand( 1000, 9999 );
            }
            if ( empty( $candidate['token'] ) ) {
                $candidate['token'] = klimacheck_generate_token();
            }
            $data['candidates'][ $candidate_id ] = $candidate;
            klimacheck_save_data( $data );

            echo '<div class="notice notice-success"><p>Candidate saved. <a href="' . esc_url( admin_url( 'admin.php?page=klimacheck' ) ) . '">Back to overview</a></p></div>';
            // Refresh candidate data after save.
            $is_edit = true;
        }
    }

    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; <?php echo $is_edit ? 'Edit' : 'Add'; ?> Candidate</h1>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>">&larr; Back to overview</a></p>

        <?php if ( ! empty( $errors ) ) : ?>
            <div class="notice notice-error">
                <ul>
                    <?php foreach ( $errors as $e ) : ?>
                        <li class="klimacheck-error"><?php echo esc_html( $e ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'klimacheck_save_candidate', 'klimacheck_candidate_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="candidate_name">Name</label></th>
                    <td><input type="text" id="candidate_name" name="candidate_name" value="<?php echo esc_attr( $candidate['name'] ); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="candidate_party">Party</label></th>
                    <td><input type="text" id="candidate_party" name="candidate_party" value="<?php echo esc_attr( $candidate['party'] ); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2>Responses</h2>

            <?php for ( $q = 1; $q <= 10; $q++ ) :
                $resp   = isset( $candidate['responses'][ $q ] ) ? $candidate['responses'][ $q ] : array( 'answer' => '', 'text' => '' );
                $answer = $resp['answer'];
                $text   = $resp['text'];
            ?>
                <div class="klimacheck-question-block">
                    <h3>Q<?php echo $q; ?>: <?php echo esc_html( $questions[ $q ]['question'] ); ?></h3>
                    <?php if ( ! empty( $questions[ $q ]['why'] ) ) : ?>
                        <p><em>Why is this important?</em> <?php echo esc_html( $questions[ $q ]['why'] ); ?></p>
                    <?php endif; ?>

                    <div class="klimacheck-radio-group">
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="yes" <?php checked( $answer, 'yes' ); ?> />
                            Yes, because&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="partially" <?php checked( $answer, 'partially' ); ?> />
                            Partially, because&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="no" <?php checked( $answer, 'no' ); ?> />
                            No, because&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="" <?php checked( $answer, '' ); ?> />
                            <span class="klimacheck-status-missing">(not answered)</span>
                        </label>
                    </div>

                    <p style="margin-top:8px;">
                        <textarea name="text_<?php echo $q; ?>" rows="3" maxlength="500" style="width:100%;" placeholder="Context / Statement (max 500 characters)"><?php echo esc_textarea( $text ); ?></textarea>
                        <span class="klimacheck-char-count"><span class="klimacheck-count-<?php echo $q; ?>"><?php echo strlen( $text ); ?></span> / 500</span>
                    </p>
                </div>
            <?php endfor; ?>

            <p>
                <button type="submit" class="button button-primary">Save Candidate</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>

    <script>
    (function(){
        document.querySelectorAll('textarea[maxlength]').forEach(function(ta){
            var countEl = ta.parentNode.querySelector('.klimacheck-char-count span');
            if (!countEl) return;
            ta.addEventListener('input', function(){
                countEl.textContent = ta.value.length;
            });
        });
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Page: Admin preview of single candidate (in WP-Admin)              */
/* ------------------------------------------------------------------ */
function klimacheck_page_review_admin() {
    $data         = klimacheck_get_data();
    $candidate_id = isset( $_GET['candidate_id'] ) ? sanitize_text_field( $_GET['candidate_id'] ) : '';
    if ( ! $candidate_id || ! isset( $data['candidates'][ $candidate_id ] ) ) {
        echo '<div class="wrap"><h1>Candidate not found.</h1></div>';
        return;
    }

    $candidate = $data['candidates'][ $candidate_id ];
    $questions = $data['questions'];

    klimacheck_render_review( $candidate, $questions, true );
}

/* ------------------------------------------------------------------ */
/*  Front-end: token-based candidate review page                       */
/* ------------------------------------------------------------------ */
add_action( 'template_redirect', 'klimacheck_frontend_review' );

function klimacheck_frontend_review() {
    if ( ! isset( $_GET['klimacheck_review'] ) ) {
        return;
    }

    $token = sanitize_text_field( $_GET['klimacheck_review'] );
    $data  = klimacheck_get_data();

    $found_candidate = null;
    foreach ( $data['candidates'] as $c ) {
        if ( isset( $c['token'] ) && $c['token'] === $token ) {
            $found_candidate = $c;
            break;
        }
    }

    if ( ! $found_candidate ) {
        wp_die( 'Invalid or expired review link.', 'KlimaCheck', array( 'response' => 403 ) );
    }

    // Render a standalone HTML page (no other candidate data exposed).
    klimacheck_render_review_frontend( $found_candidate, $data['questions'] );
    exit;
}

/* ------------------------------------------------------------------ */
/*  Shared review renderer                                             */
/* ------------------------------------------------------------------ */
function klimacheck_render_review( $candidate, $questions, $is_admin = false ) {
    $wrap_class = $is_admin ? 'wrap klimacheck-wrap' : '';
    ?>
    <div class="<?php echo esc_attr( $wrap_class ); ?>">
        <h1>KlimaCheck &mdash; Review for <?php echo esc_html( $candidate['name'] ); ?></h1>
        <?php if ( $is_admin ) : ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>">&larr; Back to overview</a></p>
        <?php endif; ?>
        <p><strong>Party:</strong> <?php echo esc_html( $candidate['party'] ); ?></p>

        <table class="klimacheck-table" style="max-width:900px;">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Question</th>
                    <th style="width:100px">Position</th>
                    <th>Statement</th>
                </tr>
            </thead>
            <tbody>
                <?php for ( $q = 1; $q <= 10; $q++ ) :
                    $resp   = isset( $candidate['responses'][ $q ] ) ? $candidate['responses'][ $q ] : array( 'answer' => '', 'text' => '' );
                    $answer = $resp['answer'];
                    $text   = $resp['text'];

                    if ( $answer === 'yes' ) {
                        $cls   = 'klimacheck-status-yes';
                        $label = 'Yes';
                    } elseif ( $answer === 'partially' ) {
                        $cls   = 'klimacheck-status-partially';
                        $label = 'Partially';
                    } elseif ( $answer === 'no' ) {
                        $cls   = 'klimacheck-status-no';
                        $label = 'No';
                    } else {
                        $cls   = 'klimacheck-status-missing';
                        $label = '—';
                    }
                ?>
                    <tr>
                        <td><?php echo $q; ?></td>
                        <td>
                            <?php echo esc_html( $questions[ $q ]['question'] ); ?>
                            <?php if ( ! empty( $questions[ $q ]['why'] ) ) : ?>
                                <br><small><em>Why important: <?php echo esc_html( $questions[ $q ]['why'] ); ?></em></small>
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo $cls; ?>"><?php echo $label; ?></td>
                        <td><?php echo esc_html( $text ); ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/** Render a standalone front-end review page (no WP admin chrome). */
function klimacheck_render_review_frontend( $candidate, $questions ) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>KlimaCheck &mdash; Review for <?php echo esc_attr( $candidate['name'] ); ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #1d2327; padding: 30px 20px; max-width: 900px; margin: 0 auto; }
            h1 { margin-bottom: 8px; font-size: 24px; }
            p { margin-bottom: 12px; }
            .klimacheck-table { border-collapse: collapse; width: 100%; margin-top: 16px; }
            .klimacheck-table th, .klimacheck-table td { border: 1px solid #ccd0d4; padding: 10px 12px; text-align: left; vertical-align: top; }
            .klimacheck-table th { background: #f0f0f1; }
            .klimacheck-status-yes { color: #00a32a; font-weight: 600; }
            .klimacheck-status-partially { color: #dba617; font-weight: 600; }
            .klimacheck-status-no { color: #d63638; font-weight: 600; }
            .klimacheck-status-missing { color: #999; }
            small { color: #666; }
            .footer { margin-top: 30px; color: #888; font-size: 13px; }
        </style>
    </head>
    <body>
        <?php klimacheck_render_review( $candidate, $questions, false ); ?>
        <p class="footer">This page shows only your personal responses. If you have corrections, please contact the KlimaCheck initiative.</p>
    </body>
    </html>
    <?php
}
