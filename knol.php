<?php
/**
 * Plugin Name: Knol
 * Plugin URI: http://wordpress.org/plugins/knol/
 * Description: This plugin allows you to show your knowledge bases directly in your Wordpress administration area. Get started by visiting Settings > Knol Settings.
 * Version: 1.28
 * Author: Knol
 * Author URI: http://knol.io/
 * License: GPLv2 or later
 */

$plugin = plugin_basename(__FILE__);

add_shortcode( 'knol_knowledge_base', 'knol_shortcode' );

function knol_shortcode($atts){
  $knowledge_base_id = get_option('knowledge_base_id');
  $knowledge_base_api_key = get_option('knowledge_base_api_key');
  $knowledge_base_update = get_option('knowledge_base_update');

  if ((time() - $knowledge_base_update) > knol_throttle()) {
    knol_get_json($knowledge_base_id, $knowledge_base_api_key);
  }

  $json_dump = file_get_contents(dirname(__FILE__)."/cache/knowledge_base_$knowledge_base_id.json");

  if (isset($_GET['article_id'])) {
    $article_id = $_GET['article_id'];
  } else {
    $article_id = 0;
  }
  
  $json_dump = json_decode($json_dump);

  if ($json_dump == null) {
?>
    <h2>Something's wrong!</h2>
    <p>
      <strong>
        The knowledge base could not be loaded. Please verify your "Knowledge Base ID" and your "Knowledge Base API Key" in Settings > Knol Settings.
      </strong>
    </p>
<?php
  } else {
    if ($article_id == 0) {
?>
      <h2>
        <?=$json_dump->knowledge_base->name ?>
      </h2>
      <p>
        <?= $json_dump->knowledge_base->content ?>
      </p>
      <h4>Other articles in this knowledge base:</h4>
      <ul>
<?php
      foreach ($json_dump->knowledge_base->articles as $article) {
?>
        <li>
          <a href="<?= add_query_arg('article_id', $article->id) ?>">
            <?=$article->name ?>
          </a>
        </li>
<?php
      }
?>
      </ul>
<?php
    } else {
      foreach ($json_dump->knowledge_base->articles as $article) {
        if ($article->id == $article_id) {
?>
          <h2>
            <?=$article->name ?>
          </h2>
          <p>
            <?= $article->content ?>
          </p>
          <p>
            <a href="<?= remove_query_arg('article_id') ?>">
              &laquo; Back to knowledge base
            </a>
          </p>
<?php
          break;
        }
      }
    }
  }
}

add_action( 'admin_menu', 'knol_menu' );
add_action( 'admin_init', 'knol_register_settings' );
add_action( 'admin_notices', 'knol_admin_notice' );
add_filter( "plugin_action_links_$plugin", 'knol_plugin_settings_link' );

function knol_plugin_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=knol_settings">Settings</a>';
  array_unshift($links, $settings_link);
  return $links;
}

function knol_menu() {  
  add_menu_page( 'Knol Main Page', 'Knowledge Base', 'manage_options', 'knol_main', 'knol_main', 'dashicons-book', 3 );
  add_options_page( 'Knol Settings', 'Knol Settings', 'manage_options', 'knol_settings', 'knol_settings');
}

function knol_register_settings() {
  register_setting( 'knol-settings', 'knowledge_base_id' );
  register_setting( 'knol-settings', 'knowledge_base_api_key' );
  register_setting( 'knol-settings', 'knowledge_base_update' );
}

function knol_settings() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
?>
  <div class="wrap">
    <h2>Knol Settings</h2>
    <form method="post" action="options.php">
<?php
      settings_fields( 'knol-settings' );
      do_settings_sections( 'knol-settings' );
      $knowledge_base_id = get_option('knowledge_base_id');
      $knowledge_base_api_key = get_option('knowledge_base_api_key');
?>
      <input type="hidden" name="knowledge_base_update" value="<?php echo time() - knol_throttle(); ?>" />
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Knowledge Base ID</th>
          <td><input type="text" name="knowledge_base_id" value="<?php echo $knowledge_base_id; ?>" /></td>
        </tr>

        <tr valign="top">
          <th scope="row">Knowledge Base API Key</th>
          <td><input type="password" name="knowledge_base_api_key" value="<?php echo $knowledge_base_api_key; ?>" /></td>
        </tr>
      </table>
<?php
      submit_button();
?>
    </form>
  </div>
  
  <div class="wrap">
    <h2>Knol Cache</h2>
    <form method="post">
      <p>The content in the knowledge base is cached for optimum performance. However, you can easily refresh the content by clicking on the button below.</p>
      <input type="hidden" name="knowledge_base_id" value="<?php echo $knowledge_base_id; ?>" />
      <input type="hidden" name="knowledge_base_api_key" value="<?php echo $knowledge_base_api_key; ?>" />
      <p class="submit"><input type="submit" name="submit_refresh_content" id="submit_refresh_content" class="button button-primary" value="Refresh Content"></p>
    </form>
  </div>

  <div class="wrap">
    <h2>What is Knol?</h2>
    <p>Knol is a beautiful private knowledge base solution. It allows you to create all your knowledge bases under one interface and easily distribute them over multiple content management systems.</p>
    <p>Prices start at $19/month. Check us out on <a href="http://knol.io/" target="_blank">knol.io</a>.</p>
  </div>
<?php
}


function knol_main() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

  $knowledge_base_id = get_option('knowledge_base_id');
  $knowledge_base_api_key = get_option('knowledge_base_api_key');
  $knowledge_base_update = get_option('knowledge_base_update');

  if ((time() - $knowledge_base_update) > knol_throttle()) {
    knol_get_json($knowledge_base_id, $knowledge_base_api_key);
  }

  $json_dump = file_get_contents(dirname(__FILE__)."/cache/knowledge_base_$knowledge_base_id.json");
  if (json_decode($json_dump) == null) {
?>
    <h2>Something's wrong!</h2>
    <p>
      <strong>
        The knowledge base could not be loaded. Please verify your "Knowledge Base ID" and your "Knowledge Base API Key" in Settings > Knol Settings.
      </strong>
    </p>
<?php
  }
  else {
?>
    <script>
      $ = jQuery.noConflict();
      var knowledge_base;

      $(function() {
        var json_data = JSON.parse("<?= addslashes($json_dump) ?>");
        knowledge_base = json_data.knowledge_base;
        refreshContent(window.location.hash);
      });

      function refreshContent(hash) {
        var knowledge_base_id = 0;
        var article_id = 0;
        var sub_menu = "";

        if (hash != "") {
          var arguments = hash.substr(1).split("-");
          knowledge_base_id = arguments[0];
          article_id = arguments[1];

          article = getArticle(article_id);
          sub_menu = displayArticle(article);
        }
        else
        {
          displayKnowledgeBase();
        }

        displaySidebar(sub_menu, article_id);
        scrollTo("#content-wrapper", 0);
      }

      function displayArticle(article) {
        var sub_menu = "";
        var i = 0;
        
        $("#title").text(article.name);
        $("#content").html(article.content);
        $("#content-wrapper").hide().fadeIn();

        $("#content h2").each(function() {
          var section_id = "section_" + i;
          var hash = "#" + knowledge_base.id + "-" + article.id;

          $(this).attr("id", section_id);
          sub_menu += "<li><a href='" + hash + "' onclick='scrollTo(\"#" + section_id + "\"); return false;'>" + $(this).text() + "</a></li>";
          
          i++;
        });

        return sub_menu
      }

      function displayKnowledgeBase() {
        $("#title").text(knowledge_base.name);
        $("#content").html(knowledge_base.content);
        $("#content-wrapper").hide().fadeIn();
      }

      function displaySidebar(sub_menu, current_article_id) {
        $("#knowledge_base").text(knowledge_base.name);
        $("#menu").html("");

        knowledge_base.articles.filter(function(article) {
          var hash = "#" + knowledge_base.id + "-" + article.id;

          $("#menu").append("<li><a href='" + hash + "' onclick='refreshContent(\"" + hash + "\")'>" + article.name + "</a></li>")
          
          if (article.id == current_article_id) {
            $("#menu").append("<li><ul id='sub-menu'>" + sub_menu + "</ul></li>")
          }
        });
      }

      function getArticle(article_id) {
        var articles = knowledge_base.articles.filter(function(item) { if (item.id == article_id) return item; })
        var article = null;
        
        if (articles.length > 0) {
          article = articles[0]
        }

        return article;
      }

      function scrollTo(element_id, speed) {
        if (speed == undefined) {
          speed = 1000;
        }

        $('body').animate({
          scrollTop: $(element_id).offset().top - 40
        }, speed);
      }

    </script>
    <style>
    #sidebar-wrapper {
      float: left;
      width: 200px;
      margin-right: 20px;
    }
      #sidebar {
        position: fixed;
        width: 200px;
      }
      #sidebar #title-link {
        text-decoration: none;
      }
      #sidebar #sub-menu, #content ul {
        list-style-type: disc;
        margin-left: 2em;
      }
    #content-wrapper {
      float: left;
      max-width: 780px;
    }
      #content-wrapper figure {
        text-align: center;
        margin-left: 0px;
        margin-right: 0px;
      }
      #content-wrapper figure img {
        max-width: 100%;
      }
    @media screen and (max-width: 1199px) {
      #sidebar-wrapper {
        float: none;
        width: 96%;
        margin-right: 0px;
      }
        #sidebar {
          position: static;
          width: 100%;
        }
      #content-wrapper {
        float: none;
        width: 96%;
      }
    }
    </style>
    
    <div id="sidebar-wrapper">
      <div id="sidebar">
        <a id="title-link" href="">
          <h3 id="knowledge_base"></h3>
        </a>
        <ul id="menu">
        </ul>
      </div>
      &nbsp;
    </div>
    
    <div id="content-wrapper">
      <h1 id="title"></h1>
      <div id="content">
      </div>
    </div>
<?php
  }
}

function knol_admin_notice() {
  $message = "";

  if (isset($_POST['submit_refresh_content'])) {
    $knowledge_base_id = $_POST['knowledge_base_id'];
    $knowledge_base_api_key = $_POST['knowledge_base_api_key'];

    if (knol_get_json($knowledge_base_id, $knowledge_base_api_key)) {
      $message = "Content refreshed!";
    }
    else {
      $message = "Your content could not be refreshed. Please verify your \"Knowledge Base ID\" and your \"Knowledge Base API Key\"";
    }
  }
  if ($message) {    
?>
    <div class="updated">
      <p><strong><?= $message ?></strong></p>
    </div>
<?php
  }
}

## HELPERS
function knol_get_json($knowledge_base_id, $knowledge_base_api_key) {
  $success = true;
  $curl = curl_init("https://docs.knol.io/api/v1/knowledge_bases/$knowledge_base_id/retrieve");
  $file = fopen(dirname(__FILE__)."/cache/knowledge_base_$knowledge_base_id.json", "w");

  curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__)."/curl/cacert.pem");
  curl_setopt($curl, CURLOPT_FILE, $file);
  curl_setopt($curl, CURLOPT_HEADER, 0);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Token token="' . $knowledge_base_api_key . '"'));

  curl_exec($curl);

  if (!curl_errno($curl)) {
    $info = curl_getinfo($curl);
    if ($info["http_code"] != 200) {
      $success = false;
    }
  }
  update_option('knowledge_base_update', time());

  curl_close($curl);
  fclose($file);

  return $success;
}

function knol_throttle() {
  return 3600;
}

?>
