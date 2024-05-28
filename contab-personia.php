<?php
/**
 * Plugin Name: Contab Personia
 * Description: Accountancy for organisations
 * Version: 1.0
 * Author: oreste@parlatano.org
 */
//------------------------------------------------------------------------------
$permitted_users = array(1);
//------------------------------------------------------------------------------
function restrict_submenu_access() {
    global $permitted_users;
    $user_id = get_current_user_id();
    if (!in_array($user_id, $permitted_users)) {
        remove_submenu_page('contab_personia', 'new_movimento');
        remove_submenu_page('contab_personia', 'api_keys');
        remove_submenu_page('contab_personia', 'api_model');
        remove_submenu_page('contab_personia', 'gerarchia');
    }
}
//------------------------------------------------------------------------------
add_action('admin_init', 'restrict_submenu_access');
//------------------------------------------------------------------------------
function contab_personia_scripts() {
    wp_enqueue_style('contab-personia-css', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('contab-personia-js', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);
	$userName = 'Utente';
	$userPic = '';
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
    	$userName = $current_user->display_name;
    	$userPic = get_avatar( $current_user->ID, 22 ); // <--- dimensione dell'immagine utente
	}    
    wp_localize_script('contab-personia-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'curr_pic' => $userPic ,
        'curr_nam' => $userName
        )
    );
}
//------------------------------------------------------------------------------
add_action('wp_enqueue_scripts', 'contab_personia_scripts');
//------------------------------------------------------------------------------
function contab_personia_admin_scripts() {
    wp_enqueue_style('contab-personia-admin-css', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('contab-personia-admin-js', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);
    $userName = 'Utente';
    $userPic = '';
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $userName = $current_user->display_name;
        $userPic = get_avatar( $current_user->ID, 22 );
    }
    wp_localize_script('contab-personia-admin-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'curr_pic' => $userPic,
        'curr_nam' => $userName
    ));
}
//------------------------------------------------------------------------------
add_action('admin_enqueue_scripts', 'contab_personia_admin_scripts');
//------------------------------------------------------------------------------
function contab_personia_activate() {
	$key = bin2hex(random_bytes(32));
    update_option('key',$key);
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = 'contab_personia_';
    $table_documenti = $table_prefix.'documenti';
    $table_piano = $table_prefix.'piano';
    $table_lista = $table_prefix.'lista';
    $table_movimenti = $table_prefix.'movimenti';
    $table_allegati = $table_prefix.'allegati';
    $table_rapporti = $table_prefix.'rapporti';
    $table_spectra = $table_prefix.'spectra';

    // Table piano
    $sql_piano = "CREATE TABLE IF NOT EXISTS $table_piano (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Codice VARCHAR(11) COMMENT 'Codice a vari livelli gerarchici.',
        Descrizione VARCHAR(255) COMMENT 'Descrizione ufficiale del codice.',
        Tipo VARCHAR(20) COMMENT 'Tipologia descritta nella letteratura specializzata.',
        Categoria VARCHAR(10) COMMENT 'Categoria descritta nella letteratura specializzata.'
    ) $charset_collate COMMENT = 'Piano dei conti aziendale per la contabilità ordinaria';";
    dbDelta($sql_piano);

    // Table movimenti
    $ref_piano = $table_piano . '(Id)' ;
    $sql_movimenti = "CREATE TABLE IF NOT EXISTS $table_movimenti (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Descrizione VARCHAR(255) COMMENT 'Descrizione del movimento contabile ed eventuale giustificazione della spesa',
        Codice INT COMMENT 'Collegamento alla lista dei codici del piano dei conti',
        Data DATE COMMENT 'Data del movimento contabile',
        Montante DECIMAL(10,2) COMMENT 'Se negativo corrisponde a delle uscite o spese viene chiamato Avere. Se positivo corrisponde a delle entrate o guadagni viene chiamato Dare.',
        FOREIGN KEY (Codice) REFERENCES $ref_piano
    ) $charset_collate COMMENT = 'Movimenti contabili gestiti come libro/giornale aziendale di contabilità ordinaria';";
    dbDelta($sql_movimenti);

    // Table allegati
    $sql_allegati = "CREATE TABLE IF NOT EXISTS $table_allegati (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Link VARCHAR(255) COMMENT 'Path relativo completo di nome del file che contiene il documento allegato',
        Movimento INT COMMENT 'Collegamento alla tabella dei movimenti'
    ) $charset_collate COMMENT = 'Associazione di uno o più documenti pdf al movimento contabile';";
    dbDelta($sql_allegati);

    // Table rapporti
    $sql_rapporti = "CREATE TABLE IF NOT EXISTS $table_rapporti (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Data DATE COMMENT 'Data ultimo rapporto che coincide con la data ultimo movimento',
        Rapporto TEXT COMMENT 'Contenuto del rapporto scritto in relazione alla tabella dei movimenti',
        Provider INT COMMENT 'Motore di IA, Id della tabella Spectra'
    ) $charset_collate COMMENT = 'Archivio dei rapporti contabili e finanziari';";
    dbDelta($sql_rapporti);

    // Table rapporti
    $sql_spectra = "CREATE TABLE IF NOT EXISTS $table_spectra (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Provider VARCHAR(100) COMMENT 'Nome formale del provider',
    	Model VARCHAR(100) COMMENT 'Modello LLM di riferimento selezionato',
        Apikey TEXT COMMENT 'Chiave API crittografata',
        Gerarchia INT COMMENT 'Ordine di chiamata del provider, se non risponde il numero 1 allora 2 e così via'
    ) $charset_collate COMMENT = 'Tabella che contiene le chiavi API dei provider.';";
    dbDelta($sql_spectra);

    // ini insert piano dei conti
    $table_name = $table_prefix.'piano';
    if ( is_table_empty($table_name) ) {
        $file_path = plugin_dir_path(__FILE__) . 'piano.csv';
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            fgetcsv($handle);
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'Codice' => $data[0],
                        'Descrizione' => $data[1],
                        'Tipo' => $data[2],
                        'Categoria' => $data[3]
                    ),
                    array('%s', '%s', '%s', '%s')
                );
            }
            fclose($handle);
        }
    }
    // end insert piano dei conti

    // --- ini cartelle ---------------------------------------
    $allegati_dir = plugin_dir_path(__FILE__) . 'allegati';
    if ( ! wp_mkdir_p( $allegati_dir ) ){
		wp_die($allegati_dir . ' non riuscito');
	}
    // --- end cartelle ---------------------------------------

}
//------------------------------------------------------------------------------
register_activation_hook(__FILE__, 'contab_personia_activate');
//------------------------------------------------------------------------------
function contab_personia_menu() {
    $contab_personia_page_hook = add_menu_page('Contabilità Personia', 'Contabilità', 'manage_options', 'contab_personia', 'contab_personia_page');
    add_submenu_page('contab_personia', 'Movimenti', 'Movimenti', 'administrator', 'movimenti', 'movimento_records');
    add_submenu_page('contab_personia', 'Archivio', 'Archivio', 'administrator', 'archivio', 'archivio');
    add_submenu_page('contab_personia', 'Esporta movimenti', 'Esporta movimenti', 'administrator', 'export', 'movimento_export');
    add_submenu_page('contab_personia', 'Piano dei conti', 'Piano dei conti', 'administrator', 'piano', 'piano_records');
    add_submenu_page('contab_personia', 'Nuovo movimento', 'Nuovo movimento', 'administrator', 'new_movimento', 'new_movimento_record');
    add_submenu_page('contab_personia', 'Legge Italiana', 'Legge Italiana', 'administrator', 'leggeit', 'legge_italiana');
    add_submenu_page('contab_personia', 'Tabella API', 'Tabella API', 'administrator', 'api_tab', 'api_tabella');
    add_submenu_page('contab_personia', 'Chiavi API', 'Chiavi API', 'administrator', 'api_keys', 'api_keys_input');
    add_submenu_page('contab_personia', 'Modello LLM API', 'Modello LLM  API', 'administrator', 'api_model', 'api_model_choice');
    add_submenu_page('contab_personia', 'Gerarchia API', 'Gerarchia API', 'administrator', 'gerarchia', 'gerarchia_api');
}
//------------------------------------------------------------------------------
add_action('admin_menu', 'contab_personia_menu');
//------------------------------------------------------------------------------
function archivio() {
    global $wpdb;
    $current_year = date('Y');
	echo '<h1>Contabilità Personia</h1>';
	echo '<h2>Archivio movimenti e rapporti</h2>';
    $sql = 'SELECT COUNT(*) FROM contab_personia_rapporti';
    $count = $wpdb->get_var($sql);
    if ($count > 0) {
        $sql = 'SELECT * FROM contab_personia_rapporti ORDER BY Data ASC';
        $rows = $wpdb->get_results($sql);
        echo '<h4>Rapporti</h4>';
        echo '<table class="personia_contab_table_contab_personia" id="personia_contab_table_code_group">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Data</th><th>Rapporto</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        $count = 0;
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td style="padding:4px;text-align:center;">';
            echo $row->Data;
            echo '</td>';
            echo '<td style="padding:4px;">';

            echo '<p><span style="cursor:pointer;user-select:none;color:red;font-weight:bold;" onclick="toggleText_'.$count.'()">&gt; </span>';
            echo '<span style="display:none;" id="expandText_'.$count.'">';
            echo $row->Rapporto;
            echo '</span></p>';
            echo '<script>function toggleText_'.$count.'() {';
            echo 'var content = document.getElementById(\'expandText_'.$count.'\');';
            echo 'if (content.style.display === \'none\') {';
            echo 'content.style.display = \'inline\';';
            echo '} else {';
            echo 'content.style.display = \'none\';';
            echo '}}</script>';

            echo '</td>';
            echo '</tr>';
            $count++;
        }
        echo '</tbody>';
        echo '</table>';
    } else {
    	echo '<p style="color:red;">Nessun rapporto presente in archivio</p>';
    }
    echo '<hr />';
    $sql = $wpdb->prepare("SELECT COUNT(*) FROM contab_personia_movimenti WHERE YEAR(Data) < %d", $current_year);
    $count = $wpdb->get_var($sql);
    if ($count > 0) {
        movimenti($current_year,2);
    } else {
    	echo '<p style="color:red;">Nessun movimento precedente all\'anno in corso</p>';
    }
}
//------------------------------------------------------------------------------
function movimento_export() {
    global $wpdb;
    $current_year = date('Y');
    $css = 'border-radius:10px;background-color:blue;color:white;box-shadow:2px 2px 4px grey;float:left;clear:both;margin-bottom:10px;margin-left:10px;';
	echo '<h1>Contabilità Personia</h1>';
	echo '<h2>Esportazione dati</h2>';
    $esportazione_dir = plugin_dir_path(__FILE__) . 'esportazione/';
    $esportazione_url = plugins_url('contab-personia') . '/' . 'esportazione/';
    $query = $wpdb->prepare("
        SELECT
            contab_personia_movimenti.Id AS 'Identificatore',
            contab_personia_movimenti.Descrizione AS 'Descrizione della spesa',
            contab_personia_piano.Codice AS 'Codice del piano dei conti',
            contab_personia_piano.Descrizione AS 'Descrizione codice',
            contab_personia_movimenti.Data AS 'Data della spesa',
            contab_personia_movimenti.Montante AS 'Spesa in Euro'
        FROM
            contab_personia_movimenti
        JOIN
            contab_personia_piano ON contab_personia_movimenti.Codice = contab_personia_piano.Id
        WHERE
        	YEAR(contab_personia_movimenti.Data) = %d", $current_year
    );
    $results = $wpdb->get_results($query);
    $csv_data = '';
    $csv_header = '"Identificatore","Descrizione della spesa","Codice del piano dei conti","Descrizione del codice","Data della spesa","Spesa in Euro"' . PHP_EOL;
    foreach ($results as $result) {
        $csv_data .= '"' . $result->{'Identificatore'} . '","' . $result->{'Descrizione della spesa'} . '","' . $result->{'Codice del piano dei conti'} . '","' . $result->{'Descrizione codice'} . '","' . $result->{'Data della spesa'} . '","' . $result->{'Spesa in Euro'} . '"' . PHP_EOL;
    }
    $csv_content = $csv_header . $csv_data;
    $url_csv = $esportazione_url.'movimenti.csv';
    $simple_name_csv = 'movimenti.csv';
    $name_csv = $esportazione_dir.$simple_name_csv;
    $file_csv = fopen($name_csv, 'w');
    fwrite($file_csv, $csv_content);
    fclose($file_csv);
    echo '<button style="'.$css.'" class="bu_contab_personia_download" value="'.$url_csv.'" name="'.$simple_name_csv.'">Esporta i dati in formato csv</button>';
}
//------------------------------------------------------------------------------
function contab_personia_page() {
    global $wpdb;
    setlocale(LC_TIME, 'it_IT');
    $table_prefix = 'contab_personia_';
    echo '<h1>Contabilità Personia</h1>';
    $query = "
    SELECT 
    P.Codice, 
    P.Descrizione, 
    SUM(M.Montante) AS Totale 
FROM 
    contab_personia_piano P 
JOIN 
    contab_personia_movimenti M 
ON 
    P.Id = M.Codice 
GROUP BY 
    P.Codice, 
    P.Descrizione

UNION ALL

SELECT 
    NULL AS Codice, 
    'Bilancio' AS Descrizione, 
    SUM(M.Montante) AS Totale 
FROM 
    contab_personia_piano P 
JOIN 
    contab_personia_movimenti M 
ON 
    P.Id = M.Codice;
    ";
    $results = $wpdb->get_results($query);
    if ($results) {
        $titolo = 'Tabella analitica del ' .  date_i18n('d F Y', strtotime(date('d/m/Y')));
        echo '<h4>'.$titolo.'</h4>';
        echo '<table class="personia_contab_table_contab_personia" id="personia_contab_table_code_group">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Codice</th><th>Descrizione</th><th>Totale</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($results as $result) {
            $cod = $result->Codice;
    		if ($cod) {
                echo '<tr>';
    		} else {
        		echo '<tr style="font-weight:bold;">';
    		}
    		echo '<td>' . $cod . '</td>';
    		echo '<td>' . $result->Descrizione . '</td>';
        	echo '<td>' . number_format($result->Totale, 2, ',', '.') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<button style="border-radius:10px;background-color:blue;color:white;box-shadow:2px 2px 4px grey;" class="bu_contab_personia_tab" title="'.$titolo.'">Stampa la tabella</button>';
        echo '<hr />';
        $table_rapporti = 'contab_personia_rapporti';
        $q_csv = 'Scrivi un rapporto contabile e finanziario sull\'analisi dei dati in formato csv seguenti: ';
        $current_year = date('Y');
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM contab_personia_movimenti WHERE YEAR(Data) = %d", $current_year);
        $count = $wpdb->get_var($sql);
        if ($count > 0) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM contab_personia_rapporti WHERE YEAR(Data) = %d", $current_year);
            $count = $wpdb->get_var($sql);
            if ($count > 0) {
                if ($count == 1) {
                    $sql = $wpdb->prepare("SELECT * FROM contab_personia_rapporti WHERE YEAR(Data) = %d LIMIT 1", $current_year);
                    $record = $wpdb->get_row($sql);
                    echo '<div style="font-famil:Arial;padding:6px;width:80%;border:solid 2px black;font-size:large;">';
                    echo '<h1>Personia SRLS</h1>';
                    echo '<h2>Rapporto contabile aggiornato al ' . date_i18n('d F Y', strtotime($record->Data)).'</h2>';
                    echo '<p>';
                    echo $record->Rapporto;
                    echo '</p><p>Milano</p>';
                    echo '</div>';
                    echo '<button style="margin-top:10px;border-radius:10px;background-color:blue;color:white;box-shadow:2px 2px 4px grey;" class="bu_contab_personia_rap" title="Stampa rapporto">Stampa il rapporto</button>';
                } else {
                    echo '<span style="color:red;font-weight:bold">Esiste più di un rapporto in archivio. Occorre cancellare i rapporti in eccesso.</span>';
                }
            } else {
                $csv = select_all_contab($current_year);
                $question = $q_csv . $csv[0];
                $answer = ask_ai($question);
                $wpdb->insert('contab_personia_rapporti',array('Data' => $csv[1],'Rapporto' => $answer[0],'Provider'=>$answer[1]));
                echo '<hr />';
                if ($wpdb->insert_id) {
                    echo '<span style="color:green;font-weight:bold">Rapporto registrato</span>';
                } else {
                    echo '<span style="color:red;font-weight:bold">Il rapporto non è stato registrato</span>';
                }
                echo '<hr />';
                echo $answer[0];
                echo '<hr />';
            }
        } else {
            echo 'Nessun movimento nell\'anno in corso';
        }
    } else {
        echo 'Non ci sono dati utili per l\'analisi contabile.';
    }
}
//------------------------------------------------------------------------------
function ask_ai($question) {
    global $wpdb;
    $system = 'Rispondi in lingua italiana, in modo impersonale, non ti riferire ad alcuna persona ma solo al tema della risposta.';
    $tabella = $wpdb->get_results("SELECT * FROM contab_personia_spectra ORDER BY Gerarchia");
    foreach ($tabella as $p) {
        $risposta = false;
        $encryptedApikey = $p->Apikey;
        $model = $p->Model;
        $provider = $p->Provider;
        $idProvider = $p->Id;
        switch ($provider) {
            case 'Mistral':
                require_once 'ai/mistral/vendor/autoload.php';
                $clientMistral = new Partitech\PhpMistral\MistralClient( decrypt_string($encryptedApikey, get_option('key')) );
                $messages = new Partitech\PhpMistral\Messages();
                $messages->addUserMessage($question);
                try {
                	$result = $clientMistral->chat($messages,['model' => $model]);
                	$answer = $result->getMessage();
                	$obj = $result->getObject();
                	$usage = $result->getUsage();
                	$risposta = true;
                } catch (Partitech\PhpMistral\MistralClientException $e) {
                	$answer = $e->getMessage();
                	$risposta = false;
                }
            break;
            case 'Anthropic':
                require_once 'ai/anthropic/vendor/autoload.php';
                $clientAnthropic = Anthropic::client( decrypt_string($encryptedApikey, get_option('key')) );
                try {
                    $response = $clientAnthropic->messages()->create([
                        'model' => $model,
                        'max_tokens' => 4096,
                        'system' => $system,
                        'messages' => [
                            ['role' => 'user', 'content' => "$question"]
                        ],
                    ]);
                    $answer = '';
                    foreach ($response->content as $result) {
                        if ($result->type == 'text') {
                            $answer .= $result->text;
                            $answer .= "\n";
                        }
                    }
                	$risposta = true;
                } catch (Exception $e) {
                    error_log($e);
                	$risposta = false;
                }
            break;
            case 'Openai':
                require_once 'ai/openai/vendor/autoload.php';
                $clientOpenai = OpenAI::client( decrypt_string($encryptedApikey, get_option('key')) );
                try {
                    $response = $clientOpenai->chat()->create([
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => "$question"]
                        ],
                    ]);
                    $answer = $response->choices[0]->message->content;
                    $risposta = true;
                } catch (Exception $e) {
                    error_log($e);
                	$risposta = false;
                }
            break;
            default:
                $risposta = false;
        }
        if ($risposta) { break; }
    }
    return [$answer,$idProvider];
}
//------------------------------------------------------------------------------
function api_tabella() {
    global $wpdb;
	echo '<h1>Contabilità Personia</h1>';
	echo '<h2>Tabella del modello LLM per ogni provider di intelligenza artificiale registrato</h2>';
    $custom_data = $wpdb->get_results("SELECT * FROM contab_personia_spectra ORDER BY Gerarchia");
    if ($custom_data) {
        echo '<table class="personia_contab_table_contab_personia" id="personia_contab_table_api">';
        echo '<thead><tr><th>Id</th><th>Provider</th><th>Modello</th><th>Chiave API</th><th>Gerarchia</th></tr></thead><tbody>';
        foreach ($custom_data as $data) {
            if ( empty($data->Apikey) ) { $akey = 'Assente'; } else { $akey = 'Presente'; }
            echo '<tr>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Id . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Provider . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Model . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $akey . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Gerarchia . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
	    echo 'Nessuno';
    }
}
//------------------------------------------------------------------------------
function api_model_choice() {
    global $wpdb;
	echo '<h1>Contabilità Personia</h1>';
	echo '<h2>Scelta del modello LLM per ogni provider di intelligenza artificiale registrato</h2>';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_models'])) {
        $model = sanitize_text_field($_POST['Anthropic']);
        $wpdb->update('contab_personia_spectra', array( 'Model' => $model ), array( 'Provider' => 'Anthropic' ) );
        $model = sanitize_text_field($_POST['Openai']);
        $wpdb->update('contab_personia_spectra', array( 'Model' => $model ), array( 'Provider' => 'Openai' ) );
        $model = sanitize_text_field($_POST['Mistral']);
        $wpdb->update('contab_personia_spectra', array( 'Model' => $model ), array( 'Provider' => 'Mistral' ) );
    }
    $custom_data = $wpdb->get_results("SELECT * FROM contab_personia_spectra ORDER BY Gerarchia");
    if ($custom_data) {
        echo '<table style="border-collapse:collapse;"><thead>';
        echo '<tr><th>Id</th><th>Provider</th><th>Modello</th><th>Chiave API</th><th>Gerarchia</th></tr></thead><tbody>';
        foreach ($custom_data as $data) {
            if ( empty($data->Apikey) ) { $akey = 'Assente'; } else { $akey = 'Presente'; }
            echo '<tr>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Id . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Provider . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Model . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $akey . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Gerarchia . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
	    echo 'Nessuno';
    }
    echo '<hr />';
	$sql = 'SELECT Id, Provider, Apikey FROM contab_personia_spectra ORDER BY Gerarchia';
    $rows = $wpdb->get_results($sql);
    $ntd = '<td colspan="'.count($rows).'">';
    $selectBox = '';
    foreach ($rows as $row) {
        $provider = $row->Provider;
        $apiKey = $row->Apikey;
        $selectBox .= '<td>'.
                        '<label for="personia_contab_'.$provider.'">'.$provider.'</label>'.
                        '<select name="'.$provider.'" id="personia_contab_'.$provider.'" required>'.
                        '<option value="" disabled selected>Scegli il modello</option>';
        $models = models($provider,$apiKey);
        $options = implode('', array_map(fn($model) => '<option value="' . $model . '">' . $model . '</option>', $models));
        $selectBox .= $options;
        $selectBox .= '</select></td>';
    }
    echo '<form method="post" action="" class="personia_contab_form_contab_personia" id="personia_contab_form_models">';
    echo '<table><tbody><tr>';
    echo $selectBox;
    echo '</tr><tr>';
    echo $ntd;
    echo '<input type="submit" name="submit_models" id="submit_models" value="Registra">';
    echo '</td></tr></tbody></table></form>';
    echo '<hr />';
}
//------------------------------------------------------------------------------
function models($provider,$encryptedApikey) {
    switch ($provider) {
        case 'Mistral':
            require_once 'ai/mistral/vendor/autoload.php';
            $clientMistral = new Partitech\PhpMistral\MistralClient( decrypt_string($encryptedApikey, get_option('key')) );
            $models = [];
            try {
                $result = $clientMistral->listModels();
                foreach ($result['data'] as $m) {
                    $models[] = $m['id'];
                }
            	return $models;
            } catch (Partitech\PhpMistral\MistralClientException $e) {
                error_log($e);
            	return ['Nessuno'];
            }
        break;
        case 'Anthropic':
            $models = ['claude-3-haiku-20240307','claude-3-sonnet-20240229','claude-3-opus-20240229'];
            return $models;
        break;
        case 'Openai':
            require_once 'ai/openai/vendor/autoload.php';
            $clientOpenai = OpenAI::client( decrypt_string($encryptedApikey, get_option('key')) );
            $models = [];
            try {
                $data = $clientOpenai->models()->list();
                foreach ($data['data'] as $m) {
                    $models[] = $m['id'];
                }
            	return $models;
            } catch (Exception $e) {
                error_log($e);
            	return ['Nessuno'];
            }
        break;
        default:
    }
}
//------------------------------------------------------------------------------
function api_keys_input() {
    global $wpdb;
	echo '<h4>Lista dei provider di intelligenza artificiale registrati e ordinati per gerarchia</h4>';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_apikey'])) {
        if (isset($_SESSION['form_start_time'])) {
            $start_time = $_SESSION['form_start_time'];
            $end_time = microtime(true);
            $elapsed_time = $end_time - $start_time;
            $acceptable_time = 10; // seconds
            if ($elapsed_time < $acceptable_time) { exit; }
            unset($_SESSION['form_start_time']);
        }
        if ( strlen($_POST['falso']) < 0 ) { exit; }
        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);
        if ( strlen($provider) < 3 ) { exit; }
        if ( strlen($api_key) < 10 ) { exit; }
        $key = get_option('key');
        $encrypted_api_key = encrypt_string($api_key, $key);
        $qp = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM contab_personia_spectra WHERE Provider = %s", $provider ) );
        if ( $qp > 0 ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE contab_personia_spectra SET Apikey = %s WHERE Provider = %s",
                    $encrypted_api_key,
                    $provider
                )
            );
        } else {
            $max_value = $wpdb->get_var( "SELECT MAX(Gerarchia) FROM contab_personia_spectra" );
            $next_gerarchia = $max_value + 1;
            $data = array(
                'Provider' => $provider,
                'Apikey' => $encrypted_api_key,
                'Gerarchia' => $next_gerarchia
            );
            $wpdb->insert('contab_personia_spectra',$data);
        }
	}
	$key = get_option('key');
    $custom_data = $wpdb->get_results("SELECT * FROM contab_personia_spectra ORDER BY Gerarchia");
    if ($custom_data) {
        echo '<table style="border-collapse:collapse;"><thead>';
        echo '<tr><th>Provider</th></tr></thead><tbody>';
        foreach ($custom_data as $data) {
            echo '<tr>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Provider . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
	    echo 'Nessuno';
    }
    echo '<hr />';
    $_SESSION['form_start_time'] = microtime(true);

?>

<h1>Contabilità Personia - Accesso ai motori di intelligenza artificiale</h1>
<form method="post" action="" class="personia_contab_form_contab_personia" id="personia_contab_form_apikey">
<table>
<tbody>
    <tr>
        <td>
        	<label for="personia_contab_provider">Nome del provider</label>
            <select name="provider" id="personia_contab_provider" required>
                <option value="" disabled selected>Scegli il Provider</option>
                <option value="Mistral">Mistral</option>
                <option value="Anthropic">Anthropic</option>
                <option value="Openai">Openai</option>
            </select>
        </td>
    </tr>
    <tr>
        <td>
        	<label for="personia_contab_apikey">Chiave API</label>
        	<input type="text" minlength="10" name="api_key" id="personia_contab_apikey" required>
        </td>
    </tr>
    <tr style="display:none;">
        <td>
        	<label for="personia_contab_falso">Falso</label>
        	<input type="text" name="falso" id="personia_contab_falso">
        </td>
    </tr>
    <tr>
        <td>
        	<input type="submit" name="submit_apikey" id="submit_apikey" value="Registra">
        </td>
    </tr>
</tbody>
</table>
</form>
<hr />

<?php

}
//------------------------------------------------------------------------------
function gerarchia_api() {
    global $wpdb;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_gerarchia'])) {
        $gerarchia = sanitize_text_field($_POST['gerarchia']);
        $errori = [];
        if ( strlen($gerarchia) < 3 ) {
			$errori[] = 'La sequenza è troppo corta';
		}
		if (!ctype_digit(str_replace(',', '', $gerarchia))) {
			$errori[] = 'La sequenza non è composta da soli numeri';
		}
        $qp = $wpdb->get_var("SELECT COUNT(*) FROM contab_personia_spectra");
		$numeri = explode(',', $gerarchia);
		foreach ($numeri as $numero) {
			if ((int) $numero > $qp) {
				$errori[] = 'La sequenza contiene almeno un numero maggiore del numero di provider registrati';
				break;
			}
		}
		sort($numeri);
		if ($numeri[0] != 1) {
			$errori[] = 'Il primo numero della sequenza ordinata non è uguale a 1';
		}
		$prev_number = null;
		foreach ($numeri as $numero) {
			if ($prev_number !== null && $numero - $prev_number !== 1) {
				$errori[] = 'La sequenza ordinata contiene numeri non sequenziali';
				break;
			}
			$prev_number = $numero;
		}
		if (count($errori) > 0) {
		    echo '<h3>La sequenza indicata contiene errori dunque non è stata registrata:</h3>';
			foreach ($errori as $errore) {
				echo $errore . '<br />';
			}
		} else {
			$aGerarchia = explode(',', $gerarchia);
            $sql = 'SELECT Id FROM contab_personia_spectra ORDER BY Gerarchia';
            $ids = $wpdb->get_col($sql);
			$count = 0;
            foreach ($ids as $id) {
                $wpdb->update('contab_personia_spectra',array('Gerarchia'=>$aGerarchia[$count]),array('ID'=>$id));
                $count++;
            }
		    echo '<h3>La sequenza indicata è stata registrata</h3>';
		}
		echo '<hr />';
	}
    echo '<h1>Contabilità Personia - Gerarchia dei motori di intelligenza artificiale</h1>';
    $custom_data = $wpdb->get_results("SELECT * FROM contab_personia_spectra ORDER BY Gerarchia");
    if ($custom_data) {
        echo '<table style="border-collapse:collapse;text-align:center;"><thead>';
        echo '<tr><th>Provider</th><th>Gerarchia</th></tr></thead><tbody>';
        foreach ($custom_data as $data) {
            echo '<tr>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Provider . '</td>';
            echo '<td style="border:solid 1px black;padding:4px;">' . $data->Gerarchia . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
	    echo 'Nessun provider registrato';
    }
    echo '<hr />';

?>

<form method="post" action="" class="personia_contab_form_contab_personia" id="personia_contab_form_gerarchia">
<table>
<tbody>
    <tr>
        <td>
        	<label for="personia_contab_gerarchia">Inserire la nuova sequenza</label>
        	<input type="text" minlength="3" name="gerarchia" id="personia_contab_gerarchia" required>
        </td>
    </tr>
    <tr>
        <td>
        	<input type="submit" name="submit_gerarchia" id="submit_gerarchia" value="Modifica">
        </td>
    </tr>
    <tr>
        <td>
			Con riferimento alla lista attuale dei provider sopra mostrata inserire la sequenza numerica, i numeri sono separati da una virgola, per esempio 4,2,1,3 in questo esempio il primo provider diventa il quarto il secondo rimane il secondo il terzo diventa il primo ed il quarto diventa il terzo.
        </td>
    </tr>
</tbody>
</table>
</form>
<hr />

<?php

}
//------------------------------------------------------------------------------
function decrypt_string($encrypted, $key) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}
//------------------------------------------------------------------------------
function encrypt_string($string, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($string, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}
//------------------------------------------------------------------------------
function select_all_contab($year) {
    global $wpdb;
    $sql = "SELECT MAX(Data) AS max_date FROM contab_personia_movimenti";
    $res = $wpdb->get_results($sql);
    if ($res) {
        $max_date = $res[0]->max_date;
    } else {
        $max_date = date('Y-m-d');
    }
    $query = $wpdb->prepare("
        SELECT
            contab_personia_movimenti.Id AS 'Identificatore',
            contab_personia_movimenti.Descrizione AS 'Descrizione della spesa',
            contab_personia_piano.Codice AS 'Codice del piano dei conti',
            contab_personia_piano.Descrizione AS 'Descrizione codice',
            contab_personia_movimenti.Data AS 'Data della spesa',
            contab_personia_movimenti.Montante AS 'Spesa in Euro'
        FROM
            contab_personia_movimenti
        JOIN
            contab_personia_piano ON contab_personia_movimenti.Codice = contab_personia_piano.Id
        WHERE
        	YEAR(contab_personia_movimenti.Data) = %d", $year
    );
    $results = $wpdb->get_results($query);
    $csv_data = '';
    $csv_header = '"Identificatore","Descrizione della spesa","Codice del piano dei conti","Descrizione del codice","Data della spesa","Spesa in Euro"' . PHP_EOL;
    foreach ($results as $result) {
        $csv_data .= '"' . $result->{'Identificatore'} . '","' . $result->{'Descrizione della spesa'} . '","' . $result->{'Codice del piano dei conti'} . '","' . $result->{'Descrizione codice'} . '","' . $result->{'Data della spesa'} . '","' . $result->{'Spesa in Euro'} . '"' . PHP_EOL;
    }
    $csv_content = $csv_header . $csv_data;
    return [$csv_content,$max_date];
}
//------------------------------------------------------------------------------
function csv_to_html($csvData) {
    $lines = explode(PHP_EOL, $csvData);
    $html = '<table style="border-collapse:collapse;">';
    $html .= '<thead><tr>';
    $columns = str_getcsv(array_shift($lines));
    foreach ($columns as $column) {
        $html .= '<th style="border:solid 1px black;padding:2px;">' . htmlspecialchars($column) . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    foreach ($lines as $line) {
        $data = str_getcsv($line);
        $html .= '<tr>';
        foreach ($data as $value) {
            if ($value) {
                $value_trimmed = preg_replace('/^"|"$/', '', $value);
                $html .= '<td style="border:solid 1px black;padding:2px;">' . htmlspecialchars($value_trimmed) . '</td>';
            }
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    echo $html;
}
//------------------------------------------------------------------------------
function struttura_tabella($table_name) {
    global $wpdb;
    $table_comment_query = $wpdb->get_var($wpdb->prepare("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'", DB_NAME, $table_name));
    $titolo = $table_comment_query ? $table_comment_query : $table_name;
    echo '<h3>' . $titolo . '</h3>';
    $table_structure = $wpdb->get_results("DESCRIBE $table_name");
    $pattern = '/\((.*?)\)/';
    echo '<h4>Struttura della tabella</h4>';
    echo '<table class="personia_contab_table_contab_personia" id="personia_contab_struct_'.$table_name.'">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Campo</th><th>Tipo</th><th>Lunghezza</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Commento</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($table_structure as $field) {
        echo '<tr>';
        echo '<td>' . $field->Field . '</td>';
        echo '<td>' . $field->Type . '</td>';
        if ( strpos(strtolower($field->Type),'char') ) {
            if (preg_match($pattern, $field->Type, $matches)) {
                echo '<td>' . $matches[1] . '</td>';
            }
        } else {
            echo '<td></td>';
        }
        echo '<td>' . $field->Null . '</td>';
        echo '<td>' . $field->Key . '</td>';
        echo '<td>' . $field->Default . '</td>';
        echo '<td>' . $field->Extra . '</td>';
        $field_comment = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", DB_NAME, $table_name, $field->Field));
        echo '<td>' . ($field_comment ? $field_comment : 'No comment') . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    return $table_structure;
}
//------------------------------------------------------------------------------
function movimento_records() {
    $current_year = date('Y');
    movimenti($current_year,1);
}
//------------------------------------------------------------------------------
function movimenti($year,$condition) {
    global $wpdb;
    $table_prefix = 'contab_personia_';
    $table_name = $table_prefix . 'movimenti';
    echo '<h1>Contabilità Personia - Movimenti</h1>';
    $table_structure = struttura_tabella($table_name);
    switch ($condition) {
        case 1:
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE YEAR(Data) = %d ORDER BY Data, Id ASC", $year);
        break;
        case 2:
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE YEAR(Data) < %d ORDER BY Data, Id ASC", $year);
        break;
        case 3:
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE YEAR(Data) > %d ORDER BY Data, Id ASC", $year);
        break;
        default:
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE YEAR(Data) = %d ORDER BY Data, Id ASC", $year);
    }
    $data_result = $wpdb->get_results($sql);
    echo '<h4>Tabella riepilogativa</h4>';
    echo '<table class="personia_contab_table_contab_personia" id="personia_contab_data_'.$table_name.'">';
    echo '<thead>';
    echo '<tr>';
    foreach ($table_structure as $field) {
        echo '<th>' . $field->Field . '</th>';
    }
    echo '<th>Allegati</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($data_result as $row) {
        echo '<tr>';
        echo '<td>'.$row->Id.'</td>';
        echo '<td>'.$row->Descrizione.'</td>';
        echo '<td>';
		$foreign_sql = "SELECT Codice FROM contab_personia_piano WHERE Id = $row->Codice";
		$linked_value = $wpdb->get_var($foreign_sql);
		echo $linked_value;
		echo '</td>';
        echo '<td>'.$row->Data.'</td>';
        echo '<td style="text-align:right;">'.number_format($row->Montante, 2, ',', '.').'</td>';
		echo '<td style="text-align:center;">';
		$allegati_sql = 'SELECT Link FROM contab_personia_allegati WHERE Movimento = ' . $row->Id;
		$links_allegati = $wpdb->get_results($allegati_sql);
		foreach ($links_allegati as $row) {
			echo '<a target="_blank" href="'.$row->Link.'">PDF</a> ';
		}
		echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '<button style="border-radius:10px;background-color:blue;color:white;box-shadow:2px 2px 4px grey;" class="bu_contab_personia_tab" title="Tabella movimenti">Stampa la tabella</button>';
}
//------------------------------------------------------------------------------
function piano_records() {
    $table_prefix = 'contab_personia_';
    $table_name = $table_prefix . 'piano';
    echo '<h1>Contabilità Personia - Piano dei conti</h1>';
    db_table_display($table_name);
}
//------------------------------------------------------------------------------
function form_movimento() {
    global $wpdb;
    $table_prefix = 'contab_personia_';
    $table_movimenti = $table_prefix.'movimenti';
    $table_piano = $table_prefix.'piano';
    $piano_items = $wpdb->get_results("SELECT Id, Codice, Descrizione FROM $table_piano");
    $piano_options = '';
    foreach ($piano_items as $item) {
        $endgroup = false;
        if (strlen($item->Codice) == 4) {
            if ($endgroup) { $piano_options .= '</optgroup>'; }
            $piano_options .= '<optgroup label="'.$item->Codice.' | '.$item->Descrizione.'">';
            $endgroup = true;
        } else {
            if (strlen($item->Codice) == 6) {
                $piano_options .= '<option style="background-color:lightgrey;" value="'.$item->Id.'">'.$item->Codice.' | '.$item->Descrizione.'</option>';
            } else {
                $piano_options .= '<option value="'.$item->Id.'">'.$item->Codice.' | '.$item->Descrizione.'</option>';
            }
        }
    }
    ?>
    <h1>Contabilità Personia - Registro di un nuovo movimento</h1>
    <form method="post" action="" class="personia_contab_form_contab_personia" id="personia_contab_form_movimento">
        <table>
            <tr><td colspan="2">
                <label for="personia_contab_codice">Codice</label>
                <select name="codice" id="personia_contab_codice" required>
                    <?php echo $piano_options ?>
                </select>
            </td></tr>
            <tr><td colspan="2" style="text-align:center;">
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_01">Acquisto beni materiali</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_02">Acquisto software</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_03">Acquisto servizi</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_04">Servizi Internet</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_05">Prestito soci</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_06">Banca</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_07">Cassa</button>
                <button class="personia_contab_bu_add_piano" id="personia_contab_add_piano_08">Spese bancarie</button>
            </td></tr>
            <tr>
            <tr><td>
                <label for="personia_contab_data">Data</label>
                <input type="date" name="data" id="personia_contab_data" required>
            </td>
            <td>
                <p style="font-weight:bold;">Data di registrazione, se la data del movimento è diversa deve essere indicata nella descrizione.</p>
            </td>
            </tr>
            <tr>
                <td>
                    <label for="personia_contab_montante">Montante €</label>
                    <input type="number" step="0.01" name="montante" id="personia_contab_montante" required>
                </td>
                <td>
                    <p style="font-weight:bold;text-align:center;">Negativo = uscita, Avere.<br />Positivo = entrata, Dare.</p>
                </td>
            </tr>
            <tr><td colspan="2">
                <label for="personia_contab_descrizione">Descrizione</label>
                <input type="text" minlength="6" name="descrizione" id="personia_contab_descrizione" required>
            </td></tr>
                <td style="text-align:center;">
                    <label for="personia_contab_fileInput" class="personia_contab_custom-file-upload">
                        <span>Carica allegato</span>
                        <input type="file" id="personia_contab_fileInput" multiple>
                    </label>
            
                    <div id="personia_contab_progressBarContainer">
                        <div id="personia_contab_progressBar"></div>
                    </div>
                </td>
                <td>
                    <p style="font-weight:bold;text-align:center;">Sono ammessi solo file PDF</p>
                    <p id="upload_message"></p>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" name="submit_movimento" id="submit_movimento" value="Conferma">
                </td>
                <td>
                    <p style="font-weight:bold;">Devono essere presenti tutte le informazioni, allegati inclusi, i quali si riferiscono ai documenti di riferimento originali facsimili, quali fatture, ricevute, contratti, ecc..</p>
                    <p id="confirm_message"></p>
                </td>
            </tr>
        </table>
    </form>
    <?php
}
//------------------------------------------------------------------------------
function record_movimento($data,$montante,$codice,$descrizione,$allegati) {
    global $wpdb;
    $table_prefix = 'contab_personia_';
    $table_movimenti = $table_prefix.'movimenti';
    $sql = "INSERT INTO $table_movimenti (`Data`,`Montante`,`Codice`,`Descrizione`) VALUES (%s, %0.2f, %d, %s)";
    $psql = $wpdb->prepare($sql, $data,$montante,$codice,$descrizione);
    $result = $wpdb->query($psql);
    if ($result == false) {
        echo '<p style="font-weight:bold;font-size:large;color:red;">Operazione non riuscita, occorre ritentare.</p>';
        return false;
    } else {
        $inserted_id = $wpdb->insert_id;
        echo '<p style="font-weight:bold;font-size:large;color:green;">Movimento registrato id n. '.$inserted_id.'</p>';
        if ( count($allegati) > 0 ) {
            $table_allegati = $table_prefix.'allegati';
            foreach ($allegati as $allegato) {
                $sql2 = "INSERT INTO $table_allegati (`Link`,`Movimento`) VALUES (%s,%d)";
                $psql2 = $wpdb->prepare($sql2, $allegato,$inserted_id);
                $result2 = $wpdb->query($psql2);
                if ($result2 == false) {
                    echo '<p style="font-weight:bold;font-size:large;color:red;">Registrazione allegati non riuscita, occorre ritentare.</p>';
                    return false;
                }
            }            
        }
        return $inserted_id;
    }
}
//------------------------------------------------------------------------------
function new_movimento_record() {
    global $wpdb;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_movimento'])) {
        $descrizione = sanitize_text_field($_POST['descrizione']);
        $dateString = sanitize_text_field($_POST['data']) . ' midnight';
        $data = new DateTime($dateString);
        $sdata = $data->format('Y-m-d H:i:s');
        $montante = floatval($_POST['montante']);
        $codice = intval($_POST['codice']);
        $oggi = new DateTime('today midnight');
        $soggi = $oggi->format('Y-m-d H:i:s');
        $lend = strlen($descrizione);
        if ( strlen($descrizione) < 6 ) { exit; }
        if ($data > $oggi) { exit; }
        if ( isset($_POST['storedFileList']) ) {
            $check_post = $_POST['storedFileList'];
            if ( empty($check_post) ) {
                $allegati = [];
            } else {
                $json_post = str_replace('\"', '"', $check_post);
                $allegati = json_decode($json_post);
            }
        } else {
            $allegati = [];
        }
        record_movimento($data->format('Y-m-d'),$montante,$codice,$descrizione,$allegati);
    } else {
        form_movimento();
    }
}
//------------------------------------------------------------------------------
function get_table_info($table_name) {
    echo '<h1>Struttura della tabella '.$table_name.'</h1>';

    if (!defined('ABSPATH')) {
        require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
    }
    
    global $wpdb;
        
    $table_structure = $wpdb->get_results("DESCRIBE $table_name");
    
    $pattern = '/\((.*?)\)/';
    
    foreach ($table_structure as $field) {
        echo "<b>Field:</b> " . $field->Field . '<br />';
        echo "<b>Type:</b> " . $field->Type . '<br />';
        if ( strpos(strtolower($field->Type),'char') ) {
            if (preg_match($pattern, $field->Type, $matches)) {
                echo '<b>Length:</b> ' . $matches[1] . '<br />';
            }
        }
        echo "<b>Null:</b> " . $field->Null . '<br />';
        echo "<b>Key:</b> " . $field->Key . '<br />';
        echo "<b>Default:</b> " . $field->Default . '<br />';
        echo "<b>Extra:</b> " . $field->Extra . '<br />';
    
        $field_comment = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", DB_NAME, $table_name, $field->Field));
        echo "<b>Comment:</b> " . ($field_comment ? $field_comment : 'No comment') . '<br />';
    
        echo '<br />';
    }
    
    $table_comment_query = $wpdb->get_var($wpdb->prepare("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'", DB_NAME, $table_name));
    echo "<b>Table Comment:</b> " . ($table_comment_query ? $table_comment_query : 'No comment') . '<br />';    

}
//------------------------------------------------------------------------------
function db_table_structure($table_name) {
    global $wpdb;
    $table_comment_query = $wpdb->get_var($wpdb->prepare("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'", DB_NAME, $table_name));
    $titolo = $table_comment_query ? $table_comment_query : $table_name;
    echo '<h3>' . $titolo . '</h3>';
    $table_structure = $wpdb->get_results("DESCRIBE $table_name");
    $pattern = '/\((.*?)\)/';
    echo '<h4>Struttura della tabella</h4>';
    echo '<table class="personia_contab_table_contab_personia" id="personia_contab_struct_'.$table_name.'">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Campo</th><th>Tipo</th><th>Lunghezza</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Commento</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($table_structure as $field) {
        echo '<tr>';
        echo '<td>' . $field->Field . '</td>';
        echo '<td>' . $field->Type . '</td>';
        if ( strpos(strtolower($field->Type),'char') ) {
            if (preg_match($pattern, $field->Type, $matches)) {
                echo '<td>' . $matches[1] . '</td>';
            }
        } else {
            echo '<td></td>';
        }
        echo '<td>' . $field->Null . '</td>';
        echo '<td>' . $field->Key . '</td>';
        echo '<td>' . $field->Default . '</td>';
        echo '<td>' . $field->Extra . '</td>';
    
        $field_comment = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", DB_NAME, $table_name, $field->Field));
        echo '<td>' . ($field_comment ? $field_comment : 'No comment') . '</td>';
    
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
//------------------------------------------------------------------------------
function db_table_display($table_name, $order=false) {
    
    global $wpdb;
    
    $table_comment_query = $wpdb->get_var($wpdb->prepare("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'", DB_NAME, $table_name));
    $titolo = $table_comment_query ? $table_comment_query : $table_name;
    echo '<h3>' . $titolo . '</h3>';

    $table_structure = $wpdb->get_results("DESCRIBE $table_name");
    $pattern = '/\((.*?)\)/';

    echo '<h4>Struttura della tabella</h4>';
    echo '<table class="personia_contab_table_contab_personia" id="personia_contab_struct_'.$table_name.'">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Campo</th><th>Tipo</th><th>Lunghezza</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Commento</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($table_structure as $field) {
        echo '<tr>';
        echo '<td>' . $field->Field . '</td>';
        echo '<td>' . $field->Type . '</td>';
        if ( strpos(strtolower($field->Type),'char') ) {
            if (preg_match($pattern, $field->Type, $matches)) {
                echo '<td>' . $matches[1] . '</td>';
            }
        } else {
            echo '<td></td>';
        }
        echo '<td>' . $field->Null . '</td>';
        echo '<td>' . $field->Key . '</td>';
        echo '<td>' . $field->Default . '</td>';
        echo '<td>' . $field->Extra . '</td>';
    
        $field_comment = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", DB_NAME, $table_name, $field->Field));
        echo '<td>' . ($field_comment ? $field_comment : 'No comment') . '</td>';
    
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    if ($order) {
        $data_result = $wpdb->get_results("SELECT * FROM $table_name ORDER BY $order ASC");
    } else {
        $data_result = $wpdb->get_results("SELECT * FROM $table_name");
    }

    echo '<h4>Dati grezzi della tabella</h4>';

    echo '<table class="personia_contab_table_contab_personia" id="personia_contab_data_'.$table_name.'">';
    echo '<thead>';
    echo '<tr>';
    foreach ($table_structure as $field) {
        echo '<th>' . $field->Field . '</th>';
    }
    if ($table_name == 'contab_personia_movimenti') {
    echo '<th>Allegati</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $foreign_keys_result = $wpdb->get_results("
        SELECT
            KCU.COLUMN_NAME,
            KCU.REFERENCED_TABLE_NAME AS REFERENCED_TABLE,
            KCU.REFERENCED_COLUMN_NAME AS REFERENCED_COLUMN
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU
        WHERE
            KCU.TABLE_SCHEMA = DATABASE() AND
            KCU.TABLE_NAME = '$table_name' AND
            KCU.REFERENCED_TABLE_NAME IS NOT NULL
    ");

    $foreign_keys = array();
    foreach ($foreign_keys_result as $foreign_key) {
        $foreign_keys[$foreign_key->COLUMN_NAME] = array(
            'referenced_table' => $foreign_key->REFERENCED_TABLE,
            'referenced_column' => $foreign_key->REFERENCED_COLUMN
        );
    }

    foreach ($data_result as $row) {
        echo '<tr>';
        foreach ($row as $field => $value) {
            echo '<td>';
            if (array_key_exists($field, $foreign_keys)) {
                $foreign_sql = "SELECT " . $field . 
                                " FROM " . $foreign_keys[$field]['referenced_table'] . 
                                " WHERE Id = $value";
                $linked_value = $wpdb->get_var($foreign_sql);
                echo $linked_value;
            } else {
                echo $value;
            }
                echo '</td>';
            }
            if ($table_name == 'contab_personia_movimenti') {
                echo '<td>';
                $allegati_sql = 'SELECT Link FROM contab_personia_allegati WHERE Movimento = ' . $row->Id;
                $links_allegati = $wpdb->get_results($allegati_sql);
                foreach ($links_allegati as $row) {
                    echo '<a target="_blank" href="'.$row->Link.'">Link</a> ';
                }
                echo '</td>';
            }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';   
    
    echo '<button style="border-radius:10px;background-color:blue;color:white;box-shadow:2px 2px 4px grey;" class="bu_contab_personia_tab" title="'.$titolo.'">Stampa la tabella</button>';
}
//------------------------------------------------------------------------------
function is_table_empty($table_name) {
    global $wpdb;

    if (empty($table_name)) {
        return false;
    }
    $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name");
    $row_count = $wpdb->get_var($query);
    if ($row_count == 0) {
        return true;
    } else {
        return false;
    }
}
//------------------------------------------------------------------------------
add_action( 'wp_ajax_personia_contab_file_upload', 'personia_contab_file_upload_handler' );
//------------------------------------------------------------------------------
function personia_contab_file_upload_handler() {
    global $permitted_user;
	$date = date('Y-m-d-H-i-s');
	$year = date('Y');
	$month = date('m');
	$day = date('d');
	$upload_relative_path = 'allegati/' . $year . '/' . $month . '/' . $day . '/';
	$upload_dir = plugin_dir_path(__FILE__) . $upload_relative_path;
	$upload_url = plugins_url('contab-personia') . '/' . $upload_relative_path;
    $user_id = get_current_user_id();
    if ( $user_id !== $permitted_user ) {
        wp_send_json_error( 'Non autorizzato' );
        die();
    }
    if ( ! isset( $_FILES['files'] ) || empty( $_FILES['files'] ) ) {
        wp_send_json_error( 'Nessun file caricato' );
        die();
    }
    $uploaded_files = $_FILES['files'];
    $response = array(
        'registered' => array(),
        'uploaded' => array(),
        'errors' => array()
    );
    foreach ( $uploaded_files['name'] as $key => $file_name ) {
        $allowed_mime_types = array(
            'application/pdf'
        );
        if ( ! in_array( $uploaded_files['type'][$key], $allowed_mime_types ) ) {
            $response['errors'][] = "$file_name: Tipo di file non permesso.";
            continue;
        }
        if ( $uploaded_files['error'][$key] !== UPLOAD_ERR_OK ) {
            $response['errors'][] = "$file_name: Errore di caricamento.";
            continue;
        }
    	if ( ! wp_mkdir_p($upload_dir) ) {
    	    $response['errors'][] = "Non riesco a creare la cartella $upload_dir";
            die();
    	}
        $filename = generate_filename($upload_dir,$file_name);
        $temp_file = $_FILES["files"]["tmp_name"][$key];
        if (! move_uploaded_file($temp_file, $upload_dir . $filename)) {
            $response['errors'][] = "$filename: Caricamento non riuscito: " . $_FILES["files"]["error"][$key];
            continue;
        }
        $response['registered'][] = $upload_url . $filename;
        $response['uploaded'][] = $file_name;
    }
    wp_send_json_success( $response );
    die();
}
//------------------------------------------------------------------------------
function generate_filename($directory,$original_filename) {
    $file_name = sanitize_file_name($original_filename);
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $files = scandir($directory);
    $numeric_files = array_filter($files, function ($file) {
        return preg_match('/^[0-9]{4}(?:\.[^.]+)?$/', $file);
    });
    sort($numeric_files);
    $highest_number = 0;
    if (!empty($numeric_files)) {
        $highest_number = (int) substr(end($numeric_files), 0, 4);
    }
    $new_number = $highest_number + 1;
    $new_filename = sprintf('%04d.'.$file_ext, $new_number);
    return $new_filename;
}
//------------------------------------------------------------------------------
function legge_italiana() {
    $current_year = date('Y');
    $previous_year = $current_year - 1;
    $pre_previous_year = $current_year - 2;
	echo '<h1>Contabilità Personia</h1>';
	echo '<h2>Bilancio in forma ordinaria</h2>';
	$td = 'style="padding:4px;"';
	$tdBold = 'style="padding:4px;font-weight:bold;"';
    echo '<table class="personia_contab_table_bilancio" id="personia_contab_table_bilancio_anni">';
    echo '<tbody>';
    echo '<tr>';
    echo '<td '.$tdBold.'>Esercizio di riferimento</td>';
    echo '<td '.$td.'>inizio: 01/01/' . $previous_year . '</td>';
    echo '<td '.$td.'>fine: 31/12/' . $previous_year . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td '.$tdBold.'>Esercizio Precedente</td>';
    echo '<td '.$td.'>inizio: 01/01/' . $pre_previous_year . '</td>';
    echo '<td '.$td.'>fine: 31/12/' . $pre_previous_year . '</td>';
    echo '</tr>';
    echo '</tbody></table>';

    echo '<table class="personia_contab_table_bilancio" id="personia_contab_table_bilancio_indice">';
    echo '<tbody>';
    echo '<tr>';
    echo '<td '.$td.'>1</td><td>2</td><td>3</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td '.$td.'>1</td><td>2</td><td>3</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td '.$td.'>1</td><td>2</td><td>3</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td '.$td.'>1</td><td>2</td><td>3</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
}
//------------------------------------------------------------------------------

?>
