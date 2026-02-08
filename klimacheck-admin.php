<?php
/**
 * Plugin Name: KlimaCheck Wolfratshausen
 * Description: Admin-Seite zur Verwaltung der KlimaCheck-Kandidatenantworten für Wolfratshausen.
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
 * Die 10 Standardfragen zurückgeben. Admins können den Fragentext
 * und die "Warum ist das wichtig?"-Erklärung über die Einstellungen ändern.
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

/** Gespeicherte Daten laden (Fragen + Kandidaten). */
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

/** Daten speichern. */
function klimacheck_save_data( $data ) {
    update_option( KLIMACHECK_OPTION_KEY, $data, false );
}

/** Eindeutigen Token für Kandidaten-Review-Links generieren. */
function klimacheck_generate_token() {
    return bin2hex( random_bytes( 16 ) );
}

/* ------------------------------------------------------------------ */
/*  Admin-Menü                                                         */
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
        'Übersicht',
        'Übersicht',
        'manage_options',
        'klimacheck',
        'klimacheck_page_overview'
    );

    add_submenu_page(
        'klimacheck',
        'Fragen',
        'Fragen',
        'manage_options',
        'klimacheck-questions',
        'klimacheck_page_questions'
    );

    add_submenu_page(
        'klimacheck',
        'Kandidat',
        'Kandidat hinzufügen / bearbeiten',
        'manage_options',
        'klimacheck-candidate',
        'klimacheck_page_candidate'
    );

    // Versteckte Seite für Kandidaten-Vorschau (über Token-Link erreichbar).
    add_submenu_page(
        null, // versteckt
        'Kandidaten-Vorschau',
        'Kandidaten-Vorschau',
        'manage_options',
        'klimacheck-review',
        'klimacheck_page_review_admin'
    );
}

/* ------------------------------------------------------------------ */
/*  Admin-Styles                                                       */
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
        .klimacheck-candidate-photo { max-width: 80px; max-height: 80px; border-radius: 4px; margin-right: 10px; vertical-align: middle; }
        .klimacheck-candidate-photo-preview { max-width: 150px; max-height: 150px; border-radius: 4px; margin-top: 8px; display: block; }
        .klimacheck-full-statement { margin-top: 10px; }
        .klimacheck-full-statement label { font-weight: 600; display: block; margin-bottom: 4px; }
    </style>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Seite: Übersicht (Tabelle der Kandidaten & Fragenstatus)           */
/* ------------------------------------------------------------------ */
function klimacheck_page_overview() {
    $data = klimacheck_get_data();

    // Löschaktion verarbeiten.
    if ( isset( $_GET['action'], $_GET['candidate_id'], $_GET['_wpnonce'] )
         && $_GET['action'] === 'delete'
         && wp_verify_nonce( $_GET['_wpnonce'], 'klimacheck_delete_' . $_GET['candidate_id'] )
    ) {
        $cid = sanitize_text_field( $_GET['candidate_id'] );
        if ( isset( $data['candidates'][ $cid ] ) ) {
            unset( $data['candidates'][ $cid ] );
            klimacheck_save_data( $data );
            $data = klimacheck_get_data();
            echo '<div class="notice notice-success"><p>Kandidat gelöscht.</p></div>';
        }
    }

    // Token-Erneuerung verarbeiten.
    if ( isset( $_GET['action'], $_GET['candidate_id'], $_GET['_wpnonce'] )
         && $_GET['action'] === 'regenerate_token'
         && wp_verify_nonce( $_GET['_wpnonce'], 'klimacheck_regen_' . $_GET['candidate_id'] )
    ) {
        $cid = sanitize_text_field( $_GET['candidate_id'] );
        if ( isset( $data['candidates'][ $cid ] ) ) {
            $data['candidates'][ $cid ]['token'] = klimacheck_generate_token();
            klimacheck_save_data( $data );
            $data = klimacheck_get_data();
            echo '<div class="notice notice-success"><p>Vorschau-Link erneuert.</p></div>';
        }
    }

    $questions  = $data['questions'];
    $candidates = $data['candidates'];

    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; Kandidatenübersicht</h1>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-candidate' ) ); ?>" class="button button-primary">+ Kandidat hinzufügen</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-questions' ) ); ?>" class="button">Fragen verwalten</a>
        </p>

        <?php if ( empty( $candidates ) ) : ?>
            <p>Noch keine Kandidaten hinzugefügt.</p>
        <?php else : ?>
            <table class="klimacheck-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Partei</th>
                        <?php for ( $q = 1; $q <= 10; $q++ ) : ?>
                            <th title="<?php echo esc_attr( $questions[ $q ]['question'] ); ?>">F<?php echo $q; ?></th>
                        <?php endfor; ?>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $candidates as $cid => $c ) : ?>
                        <tr>
                            <td>
                                <?php if ( ! empty( $c['photo_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $c['photo_url'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" class="klimacheck-candidate-photo" />
                                <?php endif; ?>
                                <?php echo esc_html( $c['name'] ); ?>
                            </td>
                            <td><?php echo esc_html( $c['party'] ); ?></td>
                            <?php for ( $q = 1; $q <= 10; $q++ ) :
                                $answer = isset( $c['responses'][ $q ]['answer'] ) ? $c['responses'][ $q ]['answer'] : '';
                                $text   = isset( $c['responses'][ $q ]['text'] ) ? $c['responses'][ $q ]['text'] : '';
                                if ( $answer && $text ) {
                                    if ( $answer === 'yes' ) {
                                        $cls   = 'klimacheck-status-yes';
                                        $label = 'Ja';
                                    } elseif ( $answer === 'partially' ) {
                                        $cls   = 'klimacheck-status-partially';
                                        $label = 'Teilweise';
                                    } else {
                                        $cls   = 'klimacheck-status-no';
                                        $label = 'Nein';
                                    }
                                    echo '<td class="' . $cls . '" title="' . esc_attr( $text ) . '">' . $label . '</td>';
                                } else {
                                    echo '<td class="klimacheck-status-missing">&mdash;</td>';
                                }
                            endfor; ?>
                            <td>
                                <div class="klimacheck-actions">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-candidate&candidate_id=' . $cid ) ); ?>" class="button button-small">Bearbeiten</a>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck-review&candidate_id=' . $cid ) ); ?>" class="button button-small">Vorschau</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=klimacheck&action=delete&candidate_id=' . $cid ), 'klimacheck_delete_' . $cid ) ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('Diesen Kandidaten löschen?');">Löschen</a>
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
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=klimacheck&action=regenerate_token&candidate_id=' . $cid ), 'klimacheck_regen_' . $cid ) ); ?>" class="button button-small" title="Neuen Link generieren">&#x21bb;</a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Bewegen Sie die Maus über eine Statuszelle, um die Stellungnahme des Kandidaten zu sehen. Der Vorschau-Link unter jedem Kandidaten kann an diesen gesendet werden &mdash; er zeigt nur <strong>dessen eigene</strong> Antworten.</p>
        <?php endif; ?>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Seite: Fragen verwalten                                            */
/* ------------------------------------------------------------------ */
function klimacheck_page_questions() {
    $data = klimacheck_get_data();

    // Speichern verarbeiten.
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['klimacheck_questions_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['klimacheck_questions_nonce'], 'klimacheck_save_questions' ) ) {
            wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.' );
        }

        for ( $q = 1; $q <= 10; $q++ ) {
            $data['questions'][ $q ]['question'] = sanitize_text_field( $_POST['question_' . $q] ?? '' );
            $data['questions'][ $q ]['why']      = sanitize_textarea_field( $_POST['why_' . $q] ?? '' );
        }
        klimacheck_save_data( $data );
        echo '<div class="notice notice-success"><p>Fragen gespeichert.</p></div>';
    }

    $questions = $data['questions'];
    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; Fragen verwalten</h1>
        <form method="post">
            <?php wp_nonce_field( 'klimacheck_save_questions', 'klimacheck_questions_nonce' ); ?>
            <table class="klimacheck-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Frage</th>
                        <th>Warum ist das wichtig?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ( $q = 1; $q <= 10; $q++ ) : ?>
                        <tr>
                            <td><?php echo $q; ?></td>
                            <td>
                                <textarea name="question_<?php echo $q; ?>" rows="3" style="width:100%;"><?php echo esc_textarea( $questions[ $q ]['question'] ); ?></textarea>
                            </td>
                            <td>
                                <textarea name="why_<?php echo $q; ?>" rows="5" style="width:100%;"><?php echo esc_textarea( $questions[ $q ]['why'] ); ?></textarea>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <p><button type="submit" class="button button-primary">Fragen speichern</button></p>
        </form>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Seite: Kandidat hinzufügen / bearbeiten                            */
/* ------------------------------------------------------------------ */
function klimacheck_page_candidate() {
    $data        = klimacheck_get_data();
    $questions   = $data['questions'];
    $candidate_id = isset( $_GET['candidate_id'] ) ? sanitize_text_field( $_GET['candidate_id'] ) : '';
    $is_edit     = $candidate_id && isset( $data['candidates'][ $candidate_id ] );
    $candidate   = $is_edit ? $data['candidates'][ $candidate_id ] : array(
        'name'           => '',
        'party'          => '',
        'photo_url'      => '',
        'full_statement' => '',
        'responses'      => array(),
        'token'          => klimacheck_generate_token(),
    );

    // Ensure fields exist for older candidates.
    if ( ! isset( $candidate['photo_url'] ) )      $candidate['photo_url'] = '';
    if ( ! isset( $candidate['full_statement'] ) )  $candidate['full_statement'] = '';

    $errors = array();

    // Speichern verarbeiten.
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['klimacheck_candidate_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['klimacheck_candidate_nonce'], 'klimacheck_save_candidate' ) ) {
            wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.' );
        }

        $candidate['name']           = sanitize_text_field( $_POST['candidate_name'] ?? '' );
        $candidate['party']          = sanitize_text_field( $_POST['candidate_party'] ?? '' );
        $candidate['photo_url']      = esc_url_raw( $_POST['candidate_photo_url'] ?? '' );
        $candidate['full_statement'] = wp_kses_post( $_POST['candidate_full_statement'] ?? '' );

        if ( empty( $candidate['name'] ) ) {
            $errors[] = 'Der Name des Kandidaten ist erforderlich.';
        }

        for ( $q = 1; $q <= 10; $q++ ) {
            $answer = sanitize_text_field( $_POST[ 'answer_' . $q ] ?? '' );
            $text   = sanitize_textarea_field( $_POST[ 'text_' . $q ] ?? '' );

            if ( strlen( $text ) > 500 ) {
                $text = mb_substr( $text, 0, 500 );
            }

            if ( $answer && ! $text ) {
                $errors[] = 'Frage ' . $q . ': Eine Stellungnahme ist erforderlich, wenn eine Position gewählt wurde („weil …").';
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

            echo '<div class="notice notice-success"><p>Kandidat gespeichert. <a href="' . esc_url( admin_url( 'admin.php?page=klimacheck' ) ) . '">Zurück zur Übersicht</a></p></div>';
            // Kandidatendaten nach dem Speichern aktualisieren.
            $is_edit = true;
        }
    }

    ?>
    <div class="wrap klimacheck-wrap">
        <h1>KlimaCheck &mdash; Kandidat <?php echo $is_edit ? 'bearbeiten' : 'hinzufügen'; ?></h1>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>">&larr; Zurück zur Übersicht</a></p>

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
                    <th><label for="candidate_party">Partei</label></th>
                    <td><input type="text" id="candidate_party" name="candidate_party" value="<?php echo esc_attr( $candidate['party'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="candidate_photo_url">Foto (URL)</label></th>
                    <td>
                        <input type="url" id="candidate_photo_url" name="candidate_photo_url" value="<?php echo esc_attr( $candidate['photo_url'] ); ?>" class="regular-text" placeholder="https://beispiel.de/foto.jpg" />
                        <p class="description">URL zu einem Kandidatenfoto (optional).</p>
                        <?php if ( ! empty( $candidate['photo_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $candidate['photo_url'] ); ?>" alt="Kandidatenfoto" class="klimacheck-candidate-photo-preview" />
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2>Ausführliche Stellungnahme</h2>
            <div class="klimacheck-full-statement">
                <label for="candidate_full_statement">Hier kann eine ausführliche Stellungnahme des Kandidaten eingegeben werden (HTML erlaubt):</label>
                <?php
                wp_editor(
                    $candidate['full_statement'],
                    'candidate_full_statement',
                    array(
                        'textarea_name' => 'candidate_full_statement',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny'         => true,
                        'quicktags'     => true,
                    )
                );
                ?>
            </div>

            <h2>Antworten</h2>

            <?php for ( $q = 1; $q <= 10; $q++ ) :
                $resp   = isset( $candidate['responses'][ $q ] ) ? $candidate['responses'][ $q ] : array( 'answer' => '', 'text' => '' );
                $answer = $resp['answer'];
                $text   = $resp['text'];
            ?>
                <div class="klimacheck-question-block">
                    <h3>F<?php echo $q; ?>: <?php echo esc_html( $questions[ $q ]['question'] ); ?></h3>
                    <?php if ( ! empty( $questions[ $q ]['why'] ) ) : ?>
                        <p><em>Warum ist das wichtig?</em> <?php echo esc_html( $questions[ $q ]['why'] ); ?></p>
                    <?php endif; ?>

                    <div class="klimacheck-radio-group">
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="yes" <?php checked( $answer, 'yes' ); ?> />
                            Ja, weil&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="partially" <?php checked( $answer, 'partially' ); ?> />
                            Teilweise, weil&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="no" <?php checked( $answer, 'no' ); ?> />
                            Nein, weil&hellip;
                        </label>
                        <label>
                            <input type="radio" name="answer_<?php echo $q; ?>" value="" <?php checked( $answer, '' ); ?> />
                            <span class="klimacheck-status-missing">(nicht beantwortet)</span>
                        </label>
                    </div>

                    <p style="margin-top:8px;">
                        <textarea name="text_<?php echo $q; ?>" rows="3" maxlength="500" style="width:100%;" placeholder="Begründung / Stellungnahme (max. 500 Zeichen)"><?php echo esc_textarea( $text ); ?></textarea>
                        <span class="klimacheck-char-count"><span class="klimacheck-count-<?php echo $q; ?>"><?php echo strlen( $text ); ?></span> / 500</span>
                    </p>
                </div>
            <?php endfor; ?>

            <p>
                <button type="submit" class="button button-primary">Kandidat speichern</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>" class="button">Abbrechen</a>
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
/*  Seite: Admin-Vorschau eines einzelnen Kandidaten (im WP-Admin)     */
/* ------------------------------------------------------------------ */
function klimacheck_page_review_admin() {
    $data         = klimacheck_get_data();
    $candidate_id = isset( $_GET['candidate_id'] ) ? sanitize_text_field( $_GET['candidate_id'] ) : '';
    if ( ! $candidate_id || ! isset( $data['candidates'][ $candidate_id ] ) ) {
        echo '<div class="wrap"><h1>Kandidat nicht gefunden.</h1></div>';
        return;
    }

    $candidate = $data['candidates'][ $candidate_id ];
    $questions = $data['questions'];

    klimacheck_render_review( $candidate, $questions, true );
}

/* ------------------------------------------------------------------ */
/*  Frontend: Token-basierte Kandidaten-Vorschauseite                  */
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
        wp_die( 'Ungültiger oder abgelaufener Vorschau-Link.', 'KlimaCheck', array( 'response' => 403 ) );
    }

    // Eigenständige HTML-Seite rendern (keine anderen Kandidatendaten sichtbar).
    klimacheck_render_review_frontend( $found_candidate, $data['questions'] );
    exit;
}

/* ------------------------------------------------------------------ */
/*  Gemeinsamer Review-Renderer                                        */
/* ------------------------------------------------------------------ */
function klimacheck_render_review( $candidate, $questions, $is_admin = false ) {
    $wrap_class = $is_admin ? 'wrap klimacheck-wrap' : '';
    ?>
    <div class="<?php echo esc_attr( $wrap_class ); ?>">
        <h1>
            <?php if ( ! empty( $candidate['photo_url'] ) ) : ?>
                <img src="<?php echo esc_url( $candidate['photo_url'] ); ?>" alt="<?php echo esc_attr( $candidate['name'] ); ?>" style="max-width:60px;max-height:60px;border-radius:4px;vertical-align:middle;margin-right:10px;" />
            <?php endif; ?>
            KlimaCheck &mdash; Vorschau für <?php echo esc_html( $candidate['name'] ); ?>
        </h1>
        <?php if ( $is_admin ) : ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=klimacheck' ) ); ?>">&larr; Zurück zur Übersicht</a></p>
        <?php endif; ?>
        <p><strong>Partei:</strong> <?php echo esc_html( $candidate['party'] ); ?></p>

        <?php if ( ! empty( $candidate['full_statement'] ) ) : ?>
            <div style="background:#f9f9f9;border:1px solid #ddd;padding:14px 18px;margin-bottom:18px;border-radius:4px;">
                <h3 style="margin-top:0;">Stellungnahme</h3>
                <?php echo wp_kses_post( $candidate['full_statement'] ); ?>
            </div>
        <?php endif; ?>

        <table class="klimacheck-table" style="max-width:900px;">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Frage</th>
                    <th style="width:100px">Position</th>
                    <th>Stellungnahme</th>
                </tr>
            </thead>
            <tbody>
                <?php for ( $q = 1; $q <= 10; $q++ ) :
                    $resp   = isset( $candidate['responses'][ $q ] ) ? $candidate['responses'][ $q ] : array( 'answer' => '', 'text' => '' );
                    $answer = $resp['answer'];
                    $text   = $resp['text'];

                    if ( $answer === 'yes' ) {
                        $cls   = 'klimacheck-status-yes';
                        $label = 'Ja';
                    } elseif ( $answer === 'partially' ) {
                        $cls   = 'klimacheck-status-partially';
                        $label = 'Teilweise';
                    } elseif ( $answer === 'no' ) {
                        $cls   = 'klimacheck-status-no';
                        $label = 'Nein';
                    } else {
                        $cls   = 'klimacheck-status-missing';
                        $label = '—';
                    }
                ?>
                    <tr>
                        <td><?php echo $q; ?></td>
                        <td><?php echo esc_html( $questions[ $q ]['question'] ); ?></td>
                        <td class="<?php echo $cls; ?>"><?php echo $label; ?></td>
                        <td><?php echo esc_html( $text ); ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/** Eigenständige Frontend-Vorschauseite rendern (ohne WP-Admin-Rahmen). */
function klimacheck_render_review_frontend( $candidate, $questions ) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>KlimaCheck &mdash; Vorschau für <?php echo esc_attr( $candidate['name'] ); ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #1d2327; padding: 30px 20px; max-width: 900px; margin: 0 auto; }
            h1 { margin-bottom: 8px; font-size: 24px; }
            h3 { margin-bottom: 8px; }
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
        <p class="footer">Diese Seite zeigt nur Ihre persönlichen Antworten. Bei Korrekturbedarf wenden Sie sich bitte an die KlimaCheck-Initiative.</p>
    </body>
    </html>
    <?php
}
