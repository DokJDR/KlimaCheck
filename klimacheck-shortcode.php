<?php
/**
 * Shortcode: [klima_check_wolfratshausen]
 * Voting Advice Application (VAA) – rein clientseitig
 *
 * Extracted from klimacheck-admin.php for better maintainability.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'klima_check_wolfratshausen', 'klimacheck_shortcode_render' );

function klimacheck_shortcode_render( $atts ) {
    $data       = klimacheck_get_data();
    $questions  = $data['questions'];
    $candidates = $data['candidates'];

    // Build a clean JSON payload (no tokens, no internal IDs exposed).
    $candidates_json = array();
    foreach ( $candidates as $cid => $c ) {
        // Skip candidates without any responses.
        $has_responses = false;
        for ( $q = 1; $q <= 10; $q++ ) {
            if ( ! empty( $c['responses'][ $q ]['answer'] ) ) {
                $has_responses = true;
                break;
            }
        }
        if ( ! $has_responses ) {
            continue;
        }

        $cand = array(
            'name'           => $c['name'],
            'party'          => $c['party'],
            'photo_url'      => isset( $c['photo_url'] ) ? $c['photo_url'] : '',
            'full_statement' => isset( $c['full_statement'] ) ? wp_kses_post( $c['full_statement'] ) : '',
            'responses'      => array(),
        );
        for ( $q = 1; $q <= 10; $q++ ) {
            $resp = isset( $c['responses'][ $q ] ) ? $c['responses'][ $q ] : array( 'answer' => '', 'text' => '' );
            $cand['responses'][ $q ] = array(
                'answer' => $resp['answer'],
                'text'   => $resp['text'],
            );
        }
        $candidates_json[] = $cand;
    }

    $questions_json = array();
    for ( $q = 1; $q <= 10; $q++ ) {
        $questions_json[] = array(
            'number'   => $q,
            'question' => $questions[ $q ]['question'],
            'why'      => $questions[ $q ]['why'],
        );
    }

    $payload = wp_json_encode( array(
        'questions'  => $questions_json,
        'candidates' => $candidates_json,
    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );

    ob_start();
    ?>
    <div id="klimacheck-app"></div>

    <style>
    /* ── KlimaCheck VAA Styles ── */
    #klimacheck-app{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;color:#1d2327;line-height:1.6;max-width:800px;margin:0 auto}
    #klimacheck-app *{box-sizing:border-box}

    /* Progress bar */
    .kc-progress{width:100%;background:#e5e7eb;border-radius:9999px;height:10px;margin-bottom:8px;overflow:hidden}
    .kc-progress-bar{height:100%;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:9999px;transition:width .3s ease}
    .kc-progress-text{font-size:14px;color:#6b7280;margin-bottom:20px;text-align:right}

    /* Question card */
    .kc-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px 24px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px}
    .kc-card h2{font-size:20px;margin:0 0 6px;color:#111827}
    .kc-card .kc-why{font-size:14px;color:#6b7280;margin-bottom:20px;font-style:italic}

    /* Answer options */
    .kc-options{display:flex;flex-direction:column;gap:10px;margin-bottom:18px}
    .kc-option{display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .15s ease;font-size:16px;background:#fafafa}
    .kc-option:hover{border-color:#86efac;background:#f0fdf4}
    .kc-option.selected{border-color:#16a34a;background:#f0fdf4;font-weight:600}
    .kc-option input[type="radio"]{accent-color:#16a34a;width:18px;height:18px;flex-shrink:0}

    /* Weight checkbox */
    .kc-weight{display:flex;align-items:center;gap:8px;padding:10px 0;font-size:14px;color:#374151}
    .kc-weight input[type="checkbox"]{accent-color:#16a34a;width:16px;height:16px}
    .kc-weight label{cursor:pointer;user-select:none}

    /* Navigation */
    .kc-nav{display:flex;justify-content:space-between;gap:12px;margin-top:8px}
    .kc-btn{padding:12px 28px;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer;transition:all .15s ease}
    .kc-btn-primary{background:#16a34a;color:#fff}
    .kc-btn-primary:hover{background:#15803d}
    .kc-btn-primary:disabled{background:#9ca3af;cursor:not-allowed}
    .kc-btn-secondary{background:#f3f4f6;color:#374151;border:1px solid #d1d5db}
    .kc-btn-secondary:hover{background:#e5e7eb}
    .kc-btn-link{background:none;border:none;color:#16a34a;font-size:14px;cursor:pointer;text-decoration:underline;padding:8px 0;font-weight:500}
    .kc-btn-link:hover{color:#15803d}

    /* Intro */
    .kc-intro{text-align:center;padding:30px 0}
    .kc-intro h1{font-size:28px;margin-bottom:12px;color:#111827}
    .kc-intro p{font-size:16px;color:#6b7280;max-width:560px;margin:0 auto 24px}

    /* Results */
    .kc-results h2{font-size:24px;margin-bottom:6px;text-align:center;color:#111827}
    .kc-results .kc-results-subtitle{text-align:center;color:#6b7280;font-size:15px;margin-bottom:24px}
    .kc-result-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .kc-result-header{display:flex;align-items:center;gap:14px;padding:18px 20px;cursor:pointer;user-select:none;transition:background .15s}
    .kc-result-header:hover{background:#f9fafb}
    .kc-result-rank{font-size:22px;font-weight:700;color:#16a34a;min-width:36px;text-align:center}
    .kc-result-photo{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0}
    .kc-result-photo-placeholder{width:52px;height:52px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:22px;color:#9ca3af;flex-shrink:0}
    .kc-result-info{flex:1;min-width:0}
    .kc-result-name{font-size:17px;font-weight:600;color:#111827}
    .kc-result-party{font-size:13px;color:#6b7280}
    .kc-result-score{text-align:right}
    .kc-result-pct{font-size:24px;font-weight:700;color:#16a34a}
    .kc-result-pct-low{color:#d97706}
    .kc-result-pct-vlow{color:#dc2626}
    .kc-result-bar{width:100px;height:8px;background:#e5e7eb;border-radius:9999px;margin-top:6px;overflow:hidden}
    .kc-result-bar-fill{height:100%;border-radius:9999px;transition:width .5s ease}
    .kc-result-chevron{font-size:20px;color:#9ca3af;transition:transform .2s;flex-shrink:0;margin-left:4px}
    .kc-result-chevron.open{transform:rotate(180deg)}

    /* Detail accordion */
    .kc-detail{max-height:0;overflow:hidden;transition:max-height .3s ease;background:#f9fafb}
    .kc-detail.open{max-height:4000px}
    .kc-detail-inner{padding:16px 20px}
    .kc-detail-q{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #e5e7eb;flex-wrap:wrap}
    .kc-detail-q:last-child{border-bottom:none}
    .kc-detail-q-num{font-weight:700;color:#374151;min-width:32px;font-size:14px;padding-top:2px}
    .kc-detail-q-body{flex:1;min-width:200px}
    .kc-detail-q-text{font-size:14px;color:#374151;margin-bottom:6px;font-weight:500}
    .kc-badge{display:inline-block;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;margin-bottom:4px}
    .kc-badge-yes{background:#dcfce7;color:#166534}
    .kc-badge-partially{background:#fef3c7;color:#92400e}
    .kc-badge-no{background:#fee2e2;color:#991b1b}
    .kc-badge-skip{background:#f3f4f6;color:#6b7280}
    .kc-detail-statement{font-size:14px;color:#4b5563;margin-top:2px;line-height:1.5}
    .kc-detail-link{display:inline-block;margin-top:14px;font-size:14px;color:#16a34a;font-weight:500;text-decoration:underline}

    /* User answer row in detail accordion */
    .kc-answer-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px}
    .kc-answer-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;min-width:70px}
    .kc-answer-label-cand{color:#374151}
    .kc-answer-label-user{color:#6366f1}
    .kc-user-row{background:#eef2ff;border-radius:8px;padding:4px 10px;margin-top:2px}

    /* Restart */
    .kc-restart{text-align:center;margin-top:20px}

    /* Privacy note */
    .kc-privacy{text-align:center;font-size:12px;color:#9ca3af;margin-top:18px}

    /* ── Detailed comparison view ── */
    .kc-comparison{overflow-x:auto}
    .kc-comparison table{border-collapse:collapse;width:100%;min-width:600px}
    .kc-comparison th,.kc-comparison td{border:1px solid #e5e7eb;padding:10px 12px;text-align:center;vertical-align:top;font-size:14px}
    .kc-comparison thead th{background:#f9fafb;font-weight:600;position:sticky;top:0;z-index:1}
    .kc-comparison .kc-comp-q-cell{text-align:left;font-weight:500;background:#fff;min-width:180px}
    .kc-comparison .kc-comp-cand-header{padding:12px 8px;min-width:100px}
    .kc-comparison .kc-comp-cand-photo{width:44px;height:44px;border-radius:50%;object-fit:cover;margin:0 auto 6px;display:block;border:2px solid #e5e7eb}
    .kc-comparison .kc-comp-cand-photo-placeholder{width:44px;height:44px;border-radius:50%;background:#e5e7eb;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#9ca3af}
    .kc-comparison .kc-comp-cand-name{font-weight:600;font-size:13px;color:#111827;word-wrap:break-word;overflow-wrap:break-word}
    .kc-comparison .kc-comp-cand-party{font-size:11px;color:#6b7280}
    .kc-comparison .kc-comp-answer{font-size:12px}
    .kc-comparison .kc-comp-statement-text{font-size:11px;color:#6b7280;margin-top:4px;line-height:1.4}
    .kc-comp-statement-btn{display:inline-block;margin-top:8px;font-size:13px;color:#16a34a;cursor:pointer;text-decoration:underline;font-weight:500;background:none;border:none;padding:0}
    .kc-comp-statement-btn:hover{color:#15803d}

    /* Full statement modal */
    .kc-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px}
    .kc-modal{background:#fff;border-radius:12px;max-width:700px;width:100%;max-height:80vh;overflow-y:auto;padding:28px 24px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.2)}
    .kc-modal-close{position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#6b7280;line-height:1}
    .kc-modal-close:hover{color:#111827}
    .kc-modal h2{margin:0 0 6px;font-size:20px}
    .kc-modal .kc-modal-party{font-size:14px;color:#6b7280;margin-bottom:16px}
    .kc-modal .kc-modal-body{font-size:15px;line-height:1.7;color:#374151}
    .kc-modal .kc-modal-body p{margin:0 0 12px}
    .kc-modal .kc-modal-body p:last-child{margin-bottom:0}
    .kc-modal .kc-modal-body br{display:block;content:"";margin:4px 0}
    .kc-modal .kc-modal-body ul,.kc-modal .kc-modal-body ol{margin:0 0 12px 20px;padding:0}
    .kc-modal .kc-modal-body li{margin-bottom:4px}

    /* Comparison: hoverable truncated text */
    .kc-comp-statement-text{cursor:pointer;transition:all .15s}
    .kc-comp-statement-text:hover{color:#111827}

    /* Floating tooltip (appended to body via JS) */
    .kc-floating-tooltip{position:fixed;background:#1d2327;color:#fff;padding:12px 16px;border-radius:8px;font-size:13px;line-height:1.6;width:300px;max-height:260px;overflow-y:auto;z-index:100000;box-shadow:0 8px 24px rgba(0,0,0,.25);text-align:left;pointer-events:none;opacity:0;transition:opacity .15s}
    .kc-floating-tooltip.visible{opacity:1}

    /* Kandidatenübersicht: card layout for candidates */
    .kc-comp-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px}
    .kc-comp-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .kc-comp-card-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb}
    .kc-comp-card-photo{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0}
    .kc-comp-card-photo-placeholder{width:48px;height:48px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:20px;color:#9ca3af;flex-shrink:0}
    .kc-comp-card-info .kc-comp-card-name{font-weight:600;font-size:15px;color:#111827}
    .kc-comp-card-info .kc-comp-card-party{font-size:12px;color:#6b7280}
    .kc-comp-card-questions{display:flex;flex-direction:column;gap:10px}
    .kc-comp-card-q{display:flex;flex-direction:column;gap:4px}
    .kc-comp-card-q-label{font-size:13px;font-weight:500;color:#374151;line-height:1.4}
    .kc-comp-card-q-answer{display:flex;align-items:flex-start;gap:8px}
    .kc-comp-card-q-text{font-size:12px;color:#6b7280;line-height:1.4}
    .kc-comp-card-footer{margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb}

    /* View toggle */
    .kc-view-toggle{display:flex;justify-content:center;gap:8px;margin-bottom:20px}
    .kc-view-toggle button{padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;font-size:14px;font-weight:500;color:#374151;transition:all .15s}
    .kc-view-toggle button.active{background:#16a34a;color:#fff;border-color:#16a34a}
    .kc-view-toggle button:hover:not(.active){background:#f3f4f6}

    /* Mobile */
    @media(max-width:600px){
        .kc-card{padding:20px 16px}
        .kc-option{padding:12px 14px;font-size:15px}
        .kc-result-header{padding:14px 14px;gap:10px}
        .kc-result-bar{width:70px}
        .kc-result-pct{font-size:20px}
        .kc-result-photo,.kc-result-photo-placeholder{width:42px;height:42px}
        .kc-nav{flex-direction:column}
        .kc-btn{width:100%;text-align:center}
        .kc-intro h1{font-size:22px}
        .kc-intro p{font-size:14px}
        .kc-card h2{font-size:17px}
        .kc-results h2{font-size:20px}
        .kc-result-rank{font-size:18px;min-width:28px}
        .kc-result-name{font-size:15px}
        .kc-result-party{font-size:12px}
        .kc-result-score{min-width:60px}
        .kc-result-chevron{font-size:16px}
        .kc-detail-inner{padding:12px 14px}
        .kc-detail-q{padding:10px 0}
        .kc-modal{padding:20px 16px;margin:10px;max-height:90vh}
        .kc-modal h2{font-size:18px}
        .kc-comp-cards{grid-template-columns:1fr}
        .kc-floating-tooltip{width:240px;font-size:12px}
        .kc-comparison table{min-width:auto}
        .kc-comparison th,.kc-comparison td{padding:8px 6px;font-size:12px}
        .kc-comparison .kc-comp-cand-header{min-width:80px}
        .kc-comparison .kc-comp-q-cell{min-width:120px}
        .kc-view-toggle{flex-direction:column;align-items:center}
    }
    </style>

    <script>
    (function(){
        "use strict";

        var DATA = <?php echo $payload; ?>;
        var questions  = DATA.questions;   // array of {number, question, why}
        var candidates = DATA.candidates;  // array of {name, party, photo_url, full_statement, responses: {1:{answer,text},...}}

        var userAnswers = {};   // {questionNumber: 'yes'|'partially'|'no'}
        var userWeights = {};   // {questionNumber: true/false}
        var currentQ    = -1;   // -1 = intro, 0..9 = questions, 10 = results, 11 = detailed comparison
        var totalQ      = questions.length;
        var app         = document.getElementById('klimacheck-app');

        /* ── Helpers ── */
        function esc(str) {
            var d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        /* Floating tooltip for truncated text */
        var floatingTip = null;
        function initFloatingTooltips(container) {
            var els = container.querySelectorAll('.kc-comp-statement-text[data-fulltext]');
            for (var i = 0; i < els.length; i++) {
                els[i].addEventListener('mouseenter', function(e) {
                    var text = this.getAttribute('data-fulltext');
                    if (!text) return;
                    if (floatingTip) floatingTip.parentNode.removeChild(floatingTip);
                    floatingTip = document.createElement('div');
                    floatingTip.className = 'kc-floating-tooltip';
                    floatingTip.textContent = text;
                    document.body.appendChild(floatingTip);
                    var rect = this.getBoundingClientRect();
                    var tipW = 300;
                    var left = rect.left + rect.width / 2 - tipW / 2;
                    if (left < 8) left = 8;
                    if (left + tipW > window.innerWidth - 8) left = window.innerWidth - tipW - 8;
                    var top = rect.top - floatingTip.offsetHeight - 8;
                    if (top < 8) top = rect.bottom + 8;
                    floatingTip.style.left = left + 'px';
                    floatingTip.style.top = top + 'px';
                    setTimeout(function() { if (floatingTip) floatingTip.classList.add('visible'); }, 10);
                });
                els[i].addEventListener('mouseleave', function() {
                    if (floatingTip) {
                        floatingTip.parentNode.removeChild(floatingTip);
                        floatingTip = null;
                    }
                });
            }
        }

        function answerValue(a) {
            if (a === 'yes') return 1;
            if (a === 'partially') return 0.5;
            if (a === 'no') return 0;
            return null;
        }

        function answerLabel(a) {
            if (a === 'yes') return 'Ja';
            if (a === 'partially') return 'Teilweise';
            if (a === 'no') return 'Nein';
            return '\u2014';
        }

        function answerLabelLong(a) {
            if (a === 'yes') return 'Ja, weil\u2026';
            if (a === 'partially') return 'Teilweise, weil\u2026';
            if (a === 'no') return 'Nein, weil\u2026';
            return 'Keine Antwort';
        }

        function badgeClass(a) {
            if (a === 'yes') return 'kc-badge kc-badge-yes';
            if (a === 'partially') return 'kc-badge kc-badge-partially';
            if (a === 'no') return 'kc-badge kc-badge-no';
            return 'kc-badge kc-badge-skip';
        }

        /* ── Full statement modal ── */
        function showStatementModal(cand) {
            var overlay = document.createElement('div');
            overlay.className = 'kc-modal-overlay';
            overlay.innerHTML =
                '<div class="kc-modal">' +
                    '<button class="kc-modal-close">&times;</button>' +
                    '<h2>' + esc(cand.name) + '</h2>' +
                    '<div class="kc-modal-party">' + esc(cand.party) + '</div>' +
                    '<div class="kc-modal-body">' + (cand.full_statement || '<em>Keine ausf\u00fchrliche Stellungnahme vorhanden.</em>') + '</div>' +
                '</div>';
            document.body.appendChild(overlay);

            overlay.querySelector('.kc-modal-close').addEventListener('click', function() {
                document.body.removeChild(overlay);
            });
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });
        }

        /* ── Matching engine ── */
        function calculateScores() {
            var results = [];
            for (var ci = 0; ci < candidates.length; ci++) {
                var cand = candidates[ci];
                var totalWeightedDiff = 0;
                var totalWeight = 0;
                var validQuestions = 0;

                for (var qi = 0; qi < totalQ; qi++) {
                    var qNum = questions[qi].number;
                    var userVal = answerValue(userAnswers[qNum]);
                    var candResp = cand.responses[qNum];
                    var candVal  = candResp ? answerValue(candResp.answer) : null;

                    if (userVal === null || candVal === null) continue;

                    var weight = userWeights[qNum] ? 2 : 1;
                    var diff = Math.abs(userVal - candVal);
                    totalWeightedDiff += diff * weight;
                    totalWeight += weight;
                    validQuestions++;
                }

                var pct = 0;
                if (totalWeight > 0) {
                    pct = Math.round((1 - totalWeightedDiff / totalWeight) * 100);
                }

                results.push({
                    candidate: cand,
                    score: pct,
                    validQuestions: validQuestions
                });
            }

            results.sort(function(a, b) { return b.score - a.score; });
            return results;
        }

        /* ── Render: Intro ── */
        function renderIntro() {
            app.innerHTML =
                '<div class="kc-intro">' +
                    '<h1>KlimaCheck Wolfratshausen</h1>' +
                    '<p>Beantworten Sie 10 Fragen und finden Sie heraus, welche Kandidat:innen Ihre klimapolitischen Positionen teilen.</p>' +
                    '<p style="font-size:13px;color:#9ca3af;">Ihre Antworten bleiben auf Ihrem Ger\u00e4t \u2013 es werden keine Daten an einen Server gesendet.</p>' +
                    '<button class="kc-btn kc-btn-primary" id="kc-start">Los geht\u2019s!</button>' +
                    '<br>' +
                    '<button class="kc-btn-link" id="kc-skip-to-detail">Direkt zur Kandidaten\u00fcbersicht (ohne Check)</button>' +
                '</div>';

            document.getElementById('kc-start').addEventListener('click', function() {
                currentQ = 0;
                render();
            });

            document.getElementById('kc-skip-to-detail').addEventListener('click', function() {
                currentQ = 11;
                render();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
        }

        /* ── Render: Question ── */
        function renderQuestion() {
            var q = questions[currentQ];
            var qNum = q.number;
            var pct = Math.round(((currentQ) / totalQ) * 100);
            var selected = userAnswers[qNum] || '';

            var html =
                '<div class="kc-progress"><div class="kc-progress-bar" style="width:' + pct + '%"></div></div>' +
                '<div class="kc-progress-text">Frage ' + (currentQ + 1) + ' von ' + totalQ + '</div>' +
                '<div class="kc-card">' +
                    '<h2>F' + qNum + ': ' + esc(q.question) + '</h2>' +
                    (q.why ? '<div class="kc-why">Warum ist das wichtig? ' + esc(q.why) + '</div>' : '') +
                    '<div class="kc-options">' +
                        '<label class="kc-option' + (selected === 'yes' ? ' selected' : '') + '">' +
                            '<input type="radio" name="kc-answer" value="yes"' + (selected === 'yes' ? ' checked' : '') + '> Ja' +
                        '</label>' +
                        '<label class="kc-option' + (selected === 'partially' ? ' selected' : '') + '">' +
                            '<input type="radio" name="kc-answer" value="partially"' + (selected === 'partially' ? ' checked' : '') + '> Teilweise' +
                        '</label>' +
                        '<label class="kc-option' + (selected === 'no' ? ' selected' : '') + '">' +
                            '<input type="radio" name="kc-answer" value="no"' + (selected === 'no' ? ' checked' : '') + '> Nein' +
                        '</label>' +
                    '</div>' +
                    '<div class="kc-weight">' +
                        '<input type="checkbox" id="kc-weight-cb"' + (userWeights[qNum] ? ' checked' : '') + '>' +
                        '<label for="kc-weight-cb">Diese Frage ist mir besonders wichtig (doppelte Gewichtung)</label>' +
                    '</div>' +
                '</div>' +
                '<div class="kc-nav">' +
                    (currentQ > 0
                        ? '<button class="kc-btn kc-btn-secondary" id="kc-prev">Zur\u00fcck</button>'
                        : '<span></span>') +
                    '<button class="kc-btn kc-btn-primary" id="kc-next">' +
                        (currentQ < totalQ - 1 ? 'Weiter' : 'Ergebnis anzeigen') +
                    '</button>' +
                '</div>';

            app.innerHTML = html;

            // Wire up option selection
            var options = app.querySelectorAll('.kc-option');
            for (var i = 0; i < options.length; i++) {
                options[i].addEventListener('click', function() {
                    var radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    userAnswers[qNum] = radio.value;
                    // Update visual selection
                    var allOpts = app.querySelectorAll('.kc-option');
                    for (var j = 0; j < allOpts.length; j++) {
                        allOpts[j].classList.remove('selected');
                    }
                    this.classList.add('selected');
                });
            }

            // Weight checkbox
            document.getElementById('kc-weight-cb').addEventListener('change', function() {
                userWeights[qNum] = this.checked;
            });

            // Navigation
            var nextBtn = document.getElementById('kc-next');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (currentQ < totalQ - 1) {
                        currentQ++;
                    } else {
                        currentQ = totalQ; // results
                    }
                    render();
                    app.scrollIntoView({behavior: 'smooth', block: 'start'});
                });
            }

            var prevBtn = document.getElementById('kc-prev');
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    currentQ--;
                    render();
                    app.scrollIntoView({behavior: 'smooth', block: 'start'});
                });
            }
        }

        /* ── Render: Results (Ranking) ── */
        function renderResults() {
            var results = calculateScores();
            var hasUserAnswers = Object.keys(userAnswers).length > 0;

            var html =
                '<div class="kc-results">' +
                '<h2>Ihr Ergebnis</h2>' +
                '<p class="kc-results-subtitle">So stimmen die Kandidat:innen mit Ihren Positionen \u00fcberein:</p>';

            for (var i = 0; i < results.length; i++) {
                var r = results[i];
                var c = r.candidate;
                var pctClass = r.score >= 60 ? '' : (r.score >= 35 ? ' kc-result-pct-low' : ' kc-result-pct-vlow');
                var barColor = r.score >= 60 ? '#16a34a' : (r.score >= 35 ? '#d97706' : '#dc2626');
                var detailId = 'kc-detail-' + i;
                var chevronId = 'kc-chevron-' + i;

                html +=
                    '<div class="kc-result-card">' +
                        '<div class="kc-result-header" data-detail="' + detailId + '" data-chevron="' + chevronId + '">' +
                            '<div class="kc-result-rank">' + (i + 1) + '.</div>';

                if (c.photo_url) {
                    html += '<img class="kc-result-photo" src="' + esc(c.photo_url) + '" alt="' + esc(c.name) + '">';
                } else {
                    html += '<div class="kc-result-photo-placeholder">\uD83D\uDC64</div>';
                }

                html +=
                            '<div class="kc-result-info">' +
                                '<div class="kc-result-name">' + esc(c.name) + '</div>' +
                                '<div class="kc-result-party">' + esc(c.party) + '</div>' +
                            '</div>' +
                            '<div class="kc-result-score">' +
                                '<div class="kc-result-pct' + pctClass + '">' + r.score + '%</div>' +
                                '<div class="kc-result-bar"><div class="kc-result-bar-fill" style="width:' + r.score + '%;background:' + barColor + '"></div></div>' +
                            '</div>' +
                            '<div class="kc-result-chevron" id="' + chevronId + '">&#9660;</div>' +
                        '</div>';

                // Detail accordion
                html += '<div class="kc-detail" id="' + detailId + '"><div class="kc-detail-inner">';

                for (var qi = 0; qi < totalQ; qi++) {
                    var qObj = questions[qi];
                    var qNum = qObj.number;
                    var candResp = c.responses[qNum] || {answer: '', text: ''};
                    var userAns  = userAnswers[qNum] || '';

                    html +=
                        '<div class="kc-detail-q">' +
                            '<div class="kc-detail-q-num">F' + qNum + '</div>' +
                            '<div class="kc-detail-q-body">' +
                                '<div class="kc-detail-q-text">' + esc(qObj.question) + '</div>' +
                                '<div class="kc-answer-row">' +
                                    '<span class="kc-answer-label kc-answer-label-cand">Kandidat:</span>' +
                                    '<span class="' + badgeClass(candResp.answer) + '">' + esc(answerLabel(candResp.answer)) + '</span>' +
                                '</div>';

                    if (hasUserAnswers) {
                        html +=
                                '<div class="kc-answer-row kc-user-row">' +
                                    '<span class="kc-answer-label kc-answer-label-user">Ihre Wahl:</span>' +
                                    '<span class="' + badgeClass(userAns) + '">' + esc(answerLabel(userAns)) + '</span>' +
                                '</div>';
                    }

                    html +=
                                (candResp.text ? '<div class="kc-detail-statement">' + esc(candResp.text) + '</div>' : '') +
                            '</div>' +
                        '</div>';
                }

                // Link to full statement
                if (c.full_statement) {
                    html += '<button class="kc-comp-statement-btn" data-cand-idx="' + i + '">Ausf\u00fchrliche Stellungnahme ansehen \u2192</button>';
                }

                html += '</div></div>'; // close kc-detail-inner + kc-detail
                html += '</div>'; // close kc-result-card
            }

            html +=
                '<div class="kc-restart" style="display:flex;flex-direction:column;align-items:center;gap:8px;">' +
                    '<button class="kc-btn kc-btn-secondary" id="kc-restart">Nochmal starten</button>' +
                    '<button class="kc-btn-link" id="kc-to-detail">Alle Kandidat:innen im \u00dcberblick vergleichen</button>' +
                '</div>' +
                '<p class="kc-privacy">\uD83D\uDD12 Alle Berechnungen finden ausschlie\u00dflich in Ihrem Browser statt. Es wurden keine Daten an einen Server gesendet.</p>' +
                '</div>';

            app.innerHTML = html;

            // Accordion toggles
            var headers = app.querySelectorAll('.kc-result-header');
            for (var h = 0; h < headers.length; h++) {
                headers[h].addEventListener('click', function() {
                    var detId = this.getAttribute('data-detail');
                    var chevId = this.getAttribute('data-chevron');
                    var detail = document.getElementById(detId);
                    var chevron = document.getElementById(chevId);
                    if (detail.classList.contains('open')) {
                        detail.classList.remove('open');
                        chevron.classList.remove('open');
                    } else {
                        detail.classList.add('open');
                        chevron.classList.add('open');
                    }
                });
            }

            // Full statement buttons
            var stmtBtns = app.querySelectorAll('.kc-comp-statement-btn');
            for (var s = 0; s < stmtBtns.length; s++) {
                stmtBtns[s].addEventListener('click', function(e) {
                    e.stopPropagation();
                    var idx = parseInt(this.getAttribute('data-cand-idx'));
                    showStatementModal(results[idx].candidate);
                });
            }

            // Restart
            document.getElementById('kc-restart').addEventListener('click', function() {
                userAnswers = {};
                userWeights = {};
                currentQ = -1;
                render();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });

            // Link to detailed comparison
            document.getElementById('kc-to-detail').addEventListener('click', function() {
                currentQ = 11;
                render();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
        }

        /* ── Render: Detailed Comparison (all candidates side by side) ── */
        var compViewMode = 'cards'; // 'cards' or 'table'

        function renderComparisonTable() {
            var numCands = candidates.length;
            var html = '<div class="kc-comparison"><table>' +
                '<thead><tr><th class="kc-comp-q-cell">Frage</th>';

            for (var ci = 0; ci < numCands; ci++) {
                var c = candidates[ci];
                html += '<th class="kc-comp-cand-header">';
                if (c.photo_url) {
                    html += '<img class="kc-comp-cand-photo" src="' + esc(c.photo_url) + '" alt="' + esc(c.name) + '">';
                } else {
                    html += '<div class="kc-comp-cand-photo-placeholder">\uD83D\uDC64</div>';
                }
                html += '<div class="kc-comp-cand-name">' + esc(c.name) + '</div>';
                html += '<div class="kc-comp-cand-party">' + esc(c.party) + '</div>';
                html += '</th>';
            }
            html += '</tr></thead><tbody>';

            for (var qi = 0; qi < totalQ; qi++) {
                var qObj = questions[qi];
                var qNum = qObj.number;
                html += '<tr><td class="kc-comp-q-cell"><strong>F' + qNum + ':</strong> ' + esc(qObj.question) + '</td>';

                for (var ci2 = 0; ci2 < numCands; ci2++) {
                    var candResp = candidates[ci2].responses[qNum] || {answer: '', text: ''};
                    html += '<td class="kc-comp-answer">' +
                        '<span class="' + badgeClass(candResp.answer) + '">' + esc(candResp.text ? answerLabelLong(candResp.answer) : answerLabel(candResp.answer)) + '</span>';
                    if (candResp.text) {
                        html += '<div class="kc-comp-statement-text">' + esc(candResp.text) + '</div>';
                    }
                    html += '</td>';
                }
                html += '</tr>';
            }

            html += '<tr><td class="kc-comp-q-cell"><strong>Stellungnahme</strong></td>';
            for (var ci3 = 0; ci3 < numCands; ci3++) {
                var cand = candidates[ci3];
                if (cand.full_statement) {
                    html += '<td><button class="kc-comp-statement-btn" data-cand-global-idx="' + ci3 + '">Stellungnahme lesen</button></td>';
                } else {
                    html += '<td class="kc-comp-answer"><span class="kc-badge kc-badge-skip">\u2014</span></td>';
                }
            }
            html += '</tr></tbody></table></div>';
            return html;
        }

        function renderComparisonCards() {
            var numCands = candidates.length;
            var html = '<div class="kc-comp-cards">';

            for (var ci = 0; ci < numCands; ci++) {
                var c = candidates[ci];
                html += '<div class="kc-comp-card">';
                html += '<div class="kc-comp-card-header">';
                if (c.photo_url) {
                    html += '<img class="kc-comp-card-photo" src="' + esc(c.photo_url) + '" alt="' + esc(c.name) + '">';
                } else {
                    html += '<div class="kc-comp-card-photo-placeholder">\uD83D\uDC64</div>';
                }
                html += '<div class="kc-comp-card-info">' +
                    '<div class="kc-comp-card-name">' + esc(c.name) + '</div>' +
                    '<div class="kc-comp-card-party">' + esc(c.party) + '</div>' +
                '</div></div>';

                html += '<div class="kc-comp-card-questions">';
                for (var qi = 0; qi < totalQ; qi++) {
                    var qObj = questions[qi];
                    var qNum = qObj.number;
                    var candResp = c.responses[qNum] || {answer: '', text: ''};
                    html += '<div class="kc-comp-card-q">' +
                        '<div class="kc-comp-card-q-label"><strong>F' + qNum + ':</strong> ' + esc(qObj.question) + '</div>' +
                        '<div class="kc-comp-card-q-answer">' +
                            '<span class="' + badgeClass(candResp.answer) + '">' + esc(candResp.text ? answerLabelLong(candResp.answer) : answerLabel(candResp.answer)) + '</span>' +
                        '</div>';
                    if (candResp.text) {
                        html += '<div class="kc-comp-card-q-text">' + esc(candResp.text) + '</div>';
                    }
                    html += '</div>';
                }
                html += '</div>';

                if (c.full_statement) {
                    html += '<div class="kc-comp-card-footer">' +
                        '<button class="kc-comp-statement-btn" data-cand-global-idx="' + ci + '">Ausf\u00fchrliche Stellungnahme lesen \u2192</button>' +
                    '</div>';
                }

                html += '</div>';
            }

            html += '</div>';
            return html;
        }

        function renderComparison() {
            var html =
                '<div class="kc-results">' +
                '<h2>Kandidaten\u00fcbersicht</h2>' +
                '<p class="kc-results-subtitle">Alle Kandidat:innen und ihre Positionen im direkten Vergleich</p>' +
                '<div class="kc-view-toggle">' +
                    '<button id="kc-view-cards" class="' + (compViewMode === 'cards' ? 'active' : '') + '">Karten-Ansicht</button>' +
                    '<button id="kc-view-table" class="' + (compViewMode === 'table' ? 'active' : '') + '">Tabellen-Ansicht</button>' +
                '</div>';

            if (compViewMode === 'cards') {
                html += renderComparisonCards();
            } else {
                html += renderComparisonTable();
            }

            var hasAnswered = Object.keys(userAnswers).length > 0;
            html += '<div class="kc-restart" style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:20px;">';
            if (hasAnswered) {
                html += '<button class="kc-btn kc-btn-secondary" id="kc-back-to-ranking">Zur\u00fcck zum Ranking</button>';
            }
            html += '<button class="kc-btn kc-btn-secondary" id="kc-back-to-start">' + (hasAnswered ? 'Nochmal starten' : 'Zur\u00fcck zum Start') + '</button>';
            html += '</div></div>';

            app.innerHTML = html;

            // Init floating tooltips for table view
            initFloatingTooltips(app);

            // View toggle buttons
            document.getElementById('kc-view-cards').addEventListener('click', function() {
                compViewMode = 'cards';
                renderComparison();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
            document.getElementById('kc-view-table').addEventListener('click', function() {
                compViewMode = 'table';
                renderComparison();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });

            // Full statement buttons
            var stmtBtns = app.querySelectorAll('.kc-comp-statement-btn');
            for (var s = 0; s < stmtBtns.length; s++) {
                stmtBtns[s].addEventListener('click', function() {
                    var idx = parseInt(this.getAttribute('data-cand-global-idx'));
                    showStatementModal(candidates[idx]);
                });
            }

            // Back to ranking
            var backRank = document.getElementById('kc-back-to-ranking');
            if (backRank) {
                backRank.addEventListener('click', function() {
                    currentQ = totalQ;
                    render();
                    app.scrollIntoView({behavior: 'smooth', block: 'start'});
                });
            }

            // Back to start
            document.getElementById('kc-back-to-start').addEventListener('click', function() {
                userAnswers = {};
                userWeights = {};
                currentQ = -1;
                render();
                app.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
        }

        /* ── Main render dispatcher ── */
        function render() {
            if (currentQ === -1) {
                renderIntro();
            } else if (currentQ < totalQ) {
                renderQuestion();
            } else if (currentQ === totalQ) {
                renderResults();
            } else {
                renderComparison();
            }
        }

        // Init
        render();

    })();
    </script>
    <?php
    return ob_get_clean();
}
