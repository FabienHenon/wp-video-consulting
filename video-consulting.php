<?php
/*
Plugin Name: Video Consultings
Plugin URI: https://fabien404.fr/
Description: Plugin for video consultings
Version: 1.0.18
Author: Fabien 404
Author URI: https://fabien404.fr/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly.
}

// Création des tables dans la base de données lors de l'activation du plugin
function video_consultings_activate()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Table consulting_answers
    $table_name_answers = $wpdb->prefix . 'consulting_answers';
    $sql_answers = "CREATE TABLE $table_name_answers (
        id varchar(255) NOT NULL,
        date datetime NOT NULL,
        title varchar(255) NOT NULL,
        video_url varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_answers);

    // Table consulting_questions
    $table_name_questions = $wpdb->prefix . 'consulting_questions';
    $sql_questions = "CREATE TABLE $table_name_questions (
        id varchar(255) NOT NULL,
        client_name varchar(255) NOT NULL,
        date datetime NOT NULL,
        question longtext NOT NULL,
        category varchar(255) NOT NULL,
        answer_id varchar(255) DEFAULT NULL,
        PRIMARY KEY  (id),
        FOREIGN KEY (answer_id) REFERENCES $table_name_answers(id)
    ) $charset_collate;";
    dbDelta($sql_questions);
}
register_activation_hook(__FILE__, 'video_consultings_activate');

// Enregistrement des shortcodes
function video_consultings_register_shortcodes()
{
    add_shortcode(
        'consulting_questions',
        'video_consultings_consulting_questions_shortcode'
    );
    add_shortcode(
        'consulting_answers',
        'video_consultings_consulting_answers_shortcode'
    );
}
add_action('init', 'video_consultings_register_shortcodes');

// Shortcode consulting_questions
function video_consultings_consulting_questions_shortcode()
{
    global $wpdb;

    $table_name_questions = $wpdb->prefix . 'consulting_questions';
    $results = $wpdb->get_results(
        "SELECT * FROM $table_name_questions WHERE answer_id IS NULL ORDER BY date DESC"
    );

    ob_start();
    if ($results) {
        echo '<table class="consulting-questions">';
        echo '<tr><th class="question">Question</th><th class="who">Qui pose la question ?</th><th class="category">Catégorie</th><th class="date">Date</th></tr>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td class="question">' .
                nl2br(esc_html($row->question)) .
                '</td>';
            echo '<td class="who">' . esc_html($row->client_name) . '</td>';
            echo '<td class="category">' . esc_html($row->category) . '</td>';
            echo '<td class="date">' .
                date_i18n('d/m/Y H:i', strtotime($row->date)) .
                '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="consulting-questions-empty">Aucune question trouvée.</div>';
    }
    return ob_get_clean();
}

// Shortcode consulting_answers
function video_consultings_consulting_answers_shortcode()
{
    global $wpdb;

    $table_name_answers = $wpdb->prefix . 'consulting_answers';
    $table_name_questions = $wpdb->prefix . 'consulting_questions';

    $embed = get_option('video_consultings_video_embed');

    // Pagination
    $current_page = max(1, absint(get_query_var('paged')));
    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;

    // Query pour récupérer les réponses avec leurs questions liées
    $query = "SELECT a.*, q.client_name, q.question, q.category
              FROM $table_name_answers AS a
              LEFT JOIN $table_name_questions AS q
              ON a.id = q.answer_id
              ORDER BY a.date DESC
              LIMIT $per_page OFFSET $offset";

    $results = $wpdb->get_results($query);

    ob_start();
    if ($results) {

        foreach ($results as $row) {
            echo '<div class="consultings-accordion">';
            echo '<div class="consultings-answer-head">';
            echo '<div class="consultings-answer-head-title">' .
                esc_html($row->title) .
                '</div><div class="consultings-answer-head-date">' .
                date_i18n('d/m/Y H:i', strtotime($row->date)) .
                '</div>';
            echo '</div>';
            echo '<div class="consultings-answer-content">';

            echo '<div class="consultings-answer-content-video">' .
                str_replace('%video_url%', esc_url($row->video_url), $embed) .
                '</div>';

            // Liste des questions liées
            echo '<table class="consultings-answer-content-questions">';
            echo '<tr><th class="question">Question</th><th class="who">Qui pose la question ?</th><th class="category">Catégorie</th></tr>';

            // Vérification si des questions sont liées à cette réponse
            if (!empty($row->client_name)) {
                echo '<tr>';
                echo '<td class="question">' .
                    nl2br(esc_html($row->question)) .
                    '</td>';
                echo '<td class="who">' . esc_html($row->client_name) . '</td>';
                echo '<td class="category">' .
                    esc_html($row->category) .
                    '</td>';
                echo '</tr>';
            } else {
                echo '<tr><td colspan="3" class="empty">Aucune question trouvée.</td></tr>';
            }
            echo '</table>';

            echo '</div>';
            echo '</div>';
        }

        // Pagination
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name_answers"
        );
        $total_pages = ceil($total_items / $per_page);

        if ($total_pages > 1) {
            echo '<div class="consultings-pagination">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page,
            ]);
            echo '</div>';
        }
        ?>
        <script type="text/javascript">
            jQuery(function() {
                jQuery(".consultings-answer-head").on("click", function() {
                    jQuery(this).parent().find(".consultings-answer-content").toggle();
                });
            });
        </script>
        <?php
    } else {
        echo '<div class="consulting-answers-empty">Aucune consultation trouvée.</div>';
    }
    return ob_get_clean();
}

// Fonction pour afficher le contenu paginé des tables
function video_consultings_display_tables_content()
{
    global $wpdb;
    $table_name_questions = $wpdb->prefix . 'consulting_questions';
    $table_name_answers = $wpdb->prefix . 'consulting_answers';
    $per_page = 20;
    $current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $offset = ($current_page - 1) * $per_page;

    // Récupérer les questions paginées
    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name_questions WHERE answer_id IS NULL ORDER BY date DESC LIMIT %d OFFSET %d",
            [$per_page, $offset]
        )
    );

    // Récupérer le nombre total de questions
    $total_questions = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_name_questions WHERE answer_id IS NULL"
    );

    // Afficher la table des questions
    echo '<h2>Questions</h2>';
    if ($questions) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>Qui pose la question ?</th><th>Question</th><th>Catégorie</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($questions as $question) {
            echo '<tr>';
            echo '<td>' . esc_html($question->client_name) . '</td>';
            echo '<td>' . nl2br(esc_html($question->question)) . '</td>';
            echo '<td>' . esc_html($question->category) . '</td>';
            echo '<td>' .
                esc_html(
                    date_i18n(
                        get_option('date_format') .
                            ' ' .
                            get_option('time_format'),
                        strtotime($question->date)
                    )
                ) .
                '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Afficher la pagination pour les questions
        echo '<div class="tablenav">';
        video_consultings_display_pagination(
            $total_questions,
            $per_page,
            $current_page,
            'paged'
        );
        echo '</div>';
    } else {
        echo '<p>Aucune question pour le moment.</p>';
    }

    $per_page_a = 10;
    $current_page_a = max(
        1,
        isset($_GET['pageda']) ? absint($_GET['pageda']) : 1
    );
    $offset_a = ($current_page_a - 1) * $per_page_a;

    // Récupérer les réponses paginées
    $answers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name_answers ORDER BY date DESC LIMIT %d OFFSET %d",
            [$per_page_a, $offset_a]
        )
    );

    // Récupérer le nombre total de réponses
    $total_answers = $wpdb->get_var("SELECT COUNT(*) FROM $table_name_answers");

    $embed = get_option('video_consultings_video_embed');

    // Afficher la table des réponses
    echo '<h2>Consultations</h2>';
    if ($answers) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>Date</th><th>Titre</th></tr></thead>';
        echo '<tbody>';
        foreach ($answers as $answer) {
            echo '<tr>';
            echo '<td>' .
                esc_html(
                    date_i18n(
                        get_option('date_format') .
                            ' ' .
                            get_option('time_format'),
                        strtotime($answer->date)
                    )
                ) .
                '</td>';
            echo '<td>' . esc_html($answer->title) . '</td>';
            echo '</tr>';

            // Afficher les questions liées à la réponse
            $related_questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name_questions WHERE answer_id = %s",
                    $answer->id
                )
            );

            if ($related_questions) {
                echo '<tr><td colspan="2">';
                echo str_replace(
                    '%video_url%',
                    esc_url($answer->video_url),
                    $embed
                );
                echo '<table class="wp-list-table widefat">';
                echo '<thead><tr><th>Qui pose la question ?</th><th>Question</th><th>Catégorie</th></tr></thead>';
                echo '<tbody>';
                foreach ($related_questions as $question) {
                    echo '<tr>';
                    echo '<td>' . esc_html($question->client_name) . '</td>';
                    echo '<td>' .
                        nl2br(esc_html($question->question)) .
                        '</td>';
                    echo '<td>' . esc_html($question->category) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</td></tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        // Afficher la pagination pour les réponses
        echo '<div class="tablenav">';
        video_consultings_display_pagination(
            $total_answers,
            $per_page_a,
            $current_page_a,
            'pageda'
        );
        echo '</div>';
    } else {
        echo '<p>Aucune consultation trouvée.</p>';
    }
}

// Fonction utilitaire pour afficher la pagination
function video_consultings_display_pagination(
    $total_items,
    $per_page,
    $current_page,
    $var_name
) {
    $total_pages = ceil($total_items / $per_page);

    echo '<div class="tablenav-pages">';
    echo '<span class="displaying-num">' .
        sprintf(
            __('Affichage de %s-%s sur %s'),
            ($current_page - 1) * $per_page + 1,
            min($current_page * $per_page, $total_items),
            $total_items
        ) .
        '</span>';

    if ($total_pages > 1) {
        $pagination_args = [
            'base' => add_query_arg($var_name, '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $current_page,
            'show_all' => false,
            'end_size' => 1,
            'mid_size' => 2,
        ];

        echo paginate_links($pagination_args);
    }

    echo '</div>';
}

// Ajouter le contenu des tables à la page de réglages
function video_consultings_settings_page_content()
{
    ?>
    <div class="wrap">
    <h2>Informations</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Token Bearer utilisé pour communiquer avec l'API</th>
            <td>
                <?php echo esc_attr(get_option('video_consultings_token')); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">URL du formulaire de soumission de nouvelle consultation</th>
            <td>
                <a href="<?php echo esc_attr(
                    get_option('video_consultings_form_url')
                ); ?>" target="_blank">
                    <?php echo esc_attr(
                        get_option('video_consultings_form_url')
                    ); ?>
                </a>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Shortcode pour afficher les questions</th>
            <td>
                [consulting_questions]
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Shortcode pour afficher les consultations</th>
            <td>
                [consulting_answers]
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">URL de l'API</th>
            <td>
                /wp-json/video-consultings/v1/update-question/{id}<br/>
                /wp-json/video-consultings/v1/create-question<br/>
                /wp-json/video-consultings/v1/create-answer
            </td>
        </tr>
    </table>
    <hr />
    <h2>Paramètres</h2>
    <form method="post" action="options.php">
        <?php
        settings_fields('video_consultings_settings');
        do_settings_sections('video_consultings_settings');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Token Bearer utilisé pour communiquer avec l'API</th>
                <td><input type="text" name="video_consultings_token" value="<?php echo esc_attr(
                    get_option('video_consultings_token')
                ); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">URL du formulaire de soumission de nouvelle consultation</th>
                <td><input type="text" name="video_consultings_form_url" value="<?php echo esc_attr(
                    get_option('video_consultings_form_url')
                ); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Code d'embed pour la vidéo (utiliser le code %video_url% pour insérer l'URL de la vidéo)</th>
                <td><textarea name="video_consultings_video_embed"><?php echo esc_attr(
                    get_option('video_consultings_video_embed')
                ); ?></textarea></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <hr />
    <?php video_consultings_display_tables_content(); ?>
    </div>
    <?php
}

// Enregistrement de la page de réglages
function video_consultings_settings()
{
    add_menu_page(
        'Consultations vidéo',
        'Consultations vidéo',
        'manage_options',
        'video-consultings-settings',
        'video_consultings_settings_page_content',
        'dashicons-video-alt2'
    );
}
add_action('admin_menu', 'video_consultings_settings');

// Enregistrement des options
function video_consultings_register_settings()
{
    register_setting('video_consultings_settings', 'video_consultings_token');
    register_setting(
        'video_consultings_settings',
        'video_consultings_video_embed'
    );
    register_setting(
        'video_consultings_settings',
        'video_consultings_form_url'
    );
}
add_action('admin_init', 'video_consultings_register_settings');

// Point d'API pour créer une nouvelle entrée dans la table consulting_questions
function create_consulting_question($request)
{
    // Vérification de l'authentification
    if (!video_consultings_is_authenticated()) {
        return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
    }

    $parameters = $request->get_json_params();

    // Vérification des paramètres de requête
    if (
        empty($parameters['client_name']) ||
        empty($parameters['question']) ||
        empty($parameters['category']) ||
        empty($parameters['id']) ||
        empty($parameters['date'])
    ) {
        return new WP_Error(
            'invalid_request',
            'Invalid request. Missing required parameters.',
            ['status' => 400]
        );
    }

    // Récupération des données du formulaire
    $client_name = sanitize_text_field($parameters['client_name']);
    $question = sanitize_textarea_field($parameters['question']);
    $category = sanitize_text_field($parameters['category']);
    $date = sanitize_text_field($parameters['date']);
    $id = sanitize_text_field($parameters['id']);

    // Création de l'entrée dans la table consulting_questions
    global $wpdb;
    $table_name_questions = $wpdb->prefix . 'consulting_questions';
    $data = [
        'id' => $id,
        'client_name' => $client_name,
        'date' => $date,
        'question' => $question,
        'category' => $category,
    ];
    $wpdb->insert($table_name_questions, $data);

    // Retourner la réponse avec l'ID de la nouvelle entrée
    return ['id' => $wpdb->insert_id];
}
add_action('rest_api_init', function () {
    register_rest_route('video-consultings/v1', '/create-question', [
        'methods' => 'POST',
        'callback' => 'create_consulting_question',
        'permission_callback' => '__return_true',
    ]);
});

// Point d'API pour créer une nouvelle entrée dans la table consulting_answers
function create_consulting_answer($request)
{
    // Vérification de l'authentification
    if (!video_consultings_is_authenticated()) {
        return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
    }

    $parameters = $request->get_json_params();

    // Vérification des paramètres de requête
    if (
        empty($parameters['title']) ||
        empty($parameters['video_url']) ||
        empty($parameters['date']) ||
        empty($parameters['id'])
    ) {
        return new WP_Error(
            'invalid_request',
            'Invalid request. Missing required parameters.',
            ['status' => 400]
        );
    }

    // Récupération des données du formulaire
    $title = sanitize_text_field($parameters['title']);
    $video_url = sanitize_text_field($parameters['video_url']);
    $date = sanitize_text_field($parameters['date']);
    $id = sanitize_text_field($parameters['id']);

    // Création de l'entrée dans la table consulting_answers
    global $wpdb;
    $table_name_answers = $wpdb->prefix . 'consulting_answers';
    $data = [
        'id' => $id,
        'date' => $date,
        'title' => $title,
        'video_url' => $video_url,
    ];
    $wpdb->insert($table_name_answers, $data);

    // Retourner la réponse avec l'ID de la nouvelle entrée
    return ['id' => $wpdb->insert_id];
}
add_action('rest_api_init', function () {
    register_rest_route('video-consultings/v1', '/create-answer', [
        'methods' => 'POST',
        'callback' => 'create_consulting_answer',
        'permission_callback' => '__return_true',
    ]);
});

// Point d'API pour modifier le champ answer_id de l'entrée spécifiée dans la table consulting_questions
function update_consulting_question($request)
{
    // Vérification de l'authentification
    if (!video_consultings_is_authenticated()) {
        return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
    }

    // Vérification des paramètres de requête
    $parameters = $request->get_json_params();
    if (empty($request['id']) || empty($parameters['answer_id'])) {
        return new WP_Error(
            'invalid_request',
            'Invalid request. Missing required parameters.',
            ['status' => 400]
        );
    }

    // Récupération des données du formulaire
    $question_id = sanitize_text_field($request['id']);
    $answer_id = sanitize_text_field($parameters['answer_id']);

    // Mise à jour du champ answer_id dans la table consulting_questions
    global $wpdb;
    $table_name_questions = $wpdb->prefix . 'consulting_questions';
    $data = [
        'answer_id' => $answer_id,
    ];
    $where = [
        'id' => $question_id,
    ];
    $wpdb->update($table_name_questions, $data, $where);

    // Retourner la réponse avec l'ID de la question mise à jour
    return ['id' => $question_id];
}
add_action('rest_api_init', function () {
    register_rest_route(
        'video-consultings/v1',
        '/update-question/(?P<id>[a-zA-Z0-9_-]+)',
        [
            'methods' => 'PUT',
            'callback' => 'update_consulting_question',
            'permission_callback' => '__return_true',
        ]
    );
});

// Fonction utilitaire pour vérifier l'authentification
function video_consultings_is_authenticated()
{
    $token = isset($_SERVER['HTTP_AUTHORIZATION'])
        ? $_SERVER['HTTP_AUTHORIZATION']
        : '';

    // Récupérer le token Bearer depuis les options du plugin
    $bearer_token = get_option('video_consultings_token');

    // Vérifier si le token correspond
    if ('Bearer ' . $bearer_token === $token) {
        return true;
    }

    return false;
}
