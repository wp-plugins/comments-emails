<?php
/*
Plugin Name: Comments Emails
Description: The plugin allows to export the <strong>names</strong> and <strong>email</strong> addresses of users, who have left comments on the blog. Activate plugin and go to: <a href="tools.php?page=comments-emails">Tools - Comments Emails</a>.
Version: 0.1
Plugin URI: http://ukraya.ru/comments-emails/
Author: Aleksej Solovjov
Author URI: http://ukraya.ru
Text Domain: comments-emails
Domain Path: /languages/
License: GPL v2 or later
*/

/*  Copyright 2015 Aleksej Solovjov

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

*/ 

add_action ('admin_init', 'ce_init');
function ce_init () {       
  load_plugin_textdomain('comments-emails', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}

if (!class_exists('WP_Settings_API_Class'))
  include_once('inc/wp-settings-api-class.php');
  

function ce_get_comments_emails ($data) {
  global $wpdb;
  //$options = get_option('ug_options');
    
  if (!isset($data['fields']) )
    $fields = 'comment_author_email';
  else {
    $fields = implode(',', array_keys($data['fields']));
  }

  $res = $wpdb->get_results('
      SELECT ' . $fields .'
      FROM '. $wpdb->prefix .'comments
      WHERE comment_approved = 1 AND comment_author_email != ""
      GROUP BY comment_author_email
      ORDER BY comment_author_email ASC
      LIMIT 5000
    ',
    'ARRAY_N'
  );
    
  return $res;
}

function ce_format_emails($res, $data) {
    
  if (!isset($res) || empty($res))
    return '';

  $row_headers = ce_row_headers(array_keys($data['fields']));
  
  $delimiter = ($data['delimiter'] == 'tab') ? "\t" : $data['delimiter'];
    
  $row[] = implode( $delimiter, $row_headers );
  foreach($res as $r)
    $row[] = implode($delimiter, $r);
  
  $out = implode("\r\n", $row);

  return $out;
}

function ce_format_emails_csv($res, $data) {
    
  if (!isset($res) || empty($res))
    return '';
  
  $row_headers = ce_row_headers(array_keys($data['fields']));
  $delimiter = ($data['delimiter'] == 'tab') ? "\t" : $data['delimiter'];
    
  array_unshift( $res, $row_headers );
          
  $out = fopen("php://output", "w");
  foreach ($res as $r)
    fputcsv($out, $r, $delimiter); 
    
  fclose($out);
}

function ce_download () {
 
  if ( is_admin() && is_user_logged_in() && isset($_GET['action']) && $_GET['action'] == 'ce_download_comments_emails') {
    $options = get_option('ce_options');    
   
    $data = ce_format_post();   
    $c_type = array(
      'txt' => 'text/plain',
      'csv' => 'text/csv'
    );
    
    header('Content-Type: '.$c_type[$data['format']].'; charset=' . get_option( 'blog_charset' ));
    header('Content-Disposition: attachment; filename=comments-emails.' . $data['format']);
    // Disable caching
    header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
    header('Pragma: no-cache'); // HTTP 1.0
    header('Expires: 0'); // Proxies    
    
    $res = ce_get_comments_emails ($data);  
    
    if ($data['format'] == 'txt')
      echo ce_format_emails($res, $data);
    else
      ce_format_emails_csv($res, $data);
    
    exit();  
  }
}
add_action( 'admin_init', 'ce_download', -100 );

function ce_row_headers ($rows) {
  
  $headers = array(
    'mailchimp' => array(
      'comment_author_email' => 'Email Address',
      'comment_author' => 'First Name'
    )
  );
  foreach($rows as $row) 
    $out[] = $headers['mailchimp'][$row];
  
  return $out;
}

function ce_settings_admin_init() {
  global $ce_settings;

  $ce_settings = new WP_Settings_API_Class;
  
  $tabs = array(
    'ce_options' => array(
      'id' => 'ce_options',
      'name' => 'ce_options',
      'title' => __( 'Comments Emails', 'comments-emails' ),
      'desc' => __( '', 'comments-emails' ),
      'submit_button' => false,
      'sections' => array(
      
        'ce_section' => array(
          'id' => 'ce_section',
          'name' => 'ce_section',
          'title' => __( 'Comments Emails', 'comments-emails' ),
          'desc' => __( 'The plugin allows to export the names and email addresses of users, who have left comments on the blog. E-mail addresses of spammers will not be exported.', 'comments-emails' ),          
        )
        
      )
    )    
  ); 
  $tabs = apply_filters('ce_tabs', $tabs, $tabs); 
  
  $fields = array(
    'ce_section' => array(       
   
      array(
        'name' => 'fields',
        'label' => __( 'Fields', 'comments-emails' ),
        'desc' => __( 'Data from whitch table fields with comments (wp_comments) will be exported.', 'comments-emails' ),
        'type' => 'multicheck',
        'default' => 'comment_author_email',
        'options' => array(
          'comment_author' => __( 'Name / <small>comment_author</small>', 'comments-emails' ),
          'comment_author_email' => __( 'Email / <small>comment_author_email</small>', 'comments-emails' )
        )
      ),

      array(
        'name' => 'delimiter',
        'label' => __( 'Delimiter', 'comments-emails' ),
        'desc' => __( 'Fields delimiter. Use <code>tab</code> if you need tab-delimited list.', 'comments-emails' ),
        'default' => 'tab',
        'type' => 'text',
      ), 

      array(
        'name' => 'format',
        'label' => __( 'Format', 'comments-emails' ),
        'desc' => __( 'File format for downloading.', 'comments-emails' ),
        'type' => 'radio',
        'default' => 'txt',
        'options' => array(
          'csv' => __( 'CSV', 'comments-emails' ),
          'txt' => __( 'TXT', 'comments-emails' )
        )
      ),      
      
      array(
        'name' => 'buttons',
        'desc' => get_submit_button(__( 'Show Emails', 'comments-emails' ), 'primary', 'ce_show_comments_emails', false) .  
          '&nbsp;&nbsp;' . 
          get_submit_button(__( 'Download Emails', 'comments-emails' ), 'secondary', 'ce_download_comments_emails', false) .
          '<span id="ce_options[spinner]" class="spinner" style="display: none; float:none !important; margin: 0 5px !important;"></span>',
        'type' => 'html',
      ),
      
      array(
        'name' => 'emails',
        'label' => __( 'Emails', 'comments-emails' ),
        'desc' => __( "Found emails; if empty - nothing found.", 'comments-emails' ),
        'type' => 'textarea'
      ),                                     
    )    
         
  );
  $fields = apply_filters('ce_fields', $fields, $fields);
  
 //set sections and fields
 $ce_settings->set_option_name( 'ce_options' );
 $ce_settings->set_sections( $tabs );
 $ce_settings->set_fields( $fields );

 //initialize them
 $ce_settings->admin_init();

}
add_action( 'admin_init', 'ce_settings_admin_init' );


// Register the plugin page
function ce_admin_menu() {
  global $ce_settings_page; 
     
  $ce_settings_page = add_submenu_page( 'tools.php', __('Comments Emails', 'comments-emails'), __('Comments Emails', 'comments-emails'), 'activate_plugins', 'comments-emails', 'ce_settings_page' );
  add_action( 'admin_footer-'. $ce_settings_page, 'ce_settings_page_js' );
}
add_action( 'admin_menu', 'ce_admin_menu', 20 );

function ce_settings_page_js() {
?>
<script type="text/javascript" >
  jQuery(document).ready(function($) {

    
    $(document).on('click', '#ce_show_comments_emails, #ce_download_comments_emails', function(e){
      e.preventDefault();

      var data = {
        e: $('input[name=ce_options\\[fields\\]\\[comment_author_email\\]]:checked').val(),
        n: $('input[name=ce_options\\[fields\\]\\[comment_author\\]]:checked').val(),
        delimiter: $("#ce_options\\[delimiter\\]").val(),
        format: $('input[name=ce_options\\[format\\]]:checked').val(),
        action: $(this).attr("id")
      }; 
      
      if ($(this).attr('id') == 'ce_download_comments_emails' ) {
        
        var params = $.param( data );
        window.location.search += '&' + params;
      }
      else {
        
        $.ajax({
          url: ajaxurl,
          data: data,
          type:"POST",
          dataType: 'json',  
          beforeSend: function() {
            $("#ce_options\\[spinner\\]").css({'display': 'inline-block'});
          },            
          success: function(data) {
            $("#ce_options\\[spinner\\]").css({'display': 'inline-block'}).hide();
            if( typeof data['error'] != 'undefined' )
              $("#ce_options\\[emails\\]").val(data['error']);
            else
              $("#ce_options\\[emails\\]").val(data['success']);
          }
        });                     
      }
      
    });       
    
  }); // jQuery End
</script>
<?php
}

function ce_format_post() {
  
  if(!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'ce_show_comments_emails')
    $data = $_POST;
  else if (!empty($_GET) && isset($_GET['action']) && $_GET['action'] == 'ce_download_comments_emails')  
    $data = $_GET;
  else
    return '';
    
    $options = get_option('ce_options');
     
    if (isset($data['e']) && !empty($data['e']) ) {
      $data['fields']['comment_author_email'] = $data['e'];
      unset($data['e']);    
    }        
    if (isset($data['n']) && !empty($data['n']) ) {
      $data['fields']['comment_author'] = $data['n'];
      unset($data['n']);
    }   
    unset($data['action']);
    
    $out = wp_parse_args($data, $options);

  
  return $out;
}

add_action('wp_ajax_ce_show_comments_emails', 'ce_show_comments_emails');
function ce_show_comments_emails() {
  
  if(!empty($_POST)) {  
      
    $data = ce_format_post();

    $res = ce_get_comments_emails ($data);
    $out['success'] = ce_format_emails($res, $data);
  }
  else
    $out['error'] = 'Error!';
    
  print json_encode($out);
  exit;     
}

add_action('wp_ajax_ce_download_comments_emails', 'ce_download_comments_emails');
function ce_download_comments_emails() {

  if(!empty($_POST)) {  
      
  $data = ce_format_post();

  $res = ce_get_comments_emails ($data);
  ce_download($res, $data);      

  }  
}

// Display the plugin settings options page
function ce_settings_page() {
  global $ce_settings; 
  //delete_option('ce_options');
  echo '<div class="wrap">';
    echo '<div id="icon-options-general" class="icon32"><br /></div>';
    echo '<h2>'.__('Comments Emails', 'comments-emails').'</h2>';
 
    echo '<div id = "col-container">';  
      echo '<div id = "col-right" class = "evc">';
        echo '<div class = "evc-box">';
        ce_ad();
        echo '</div>';
      echo '</div>';
      echo '<div id = "col-left" class = "evc">';
        settings_errors();
        $ce_settings->show_navigation();
        $ce_settings->show_forms();
      echo '</div>';
    echo '</div>';  
        
  echo '</div>';
}


add_action('admin_head', 'ce_admin_head', 99 );
function ce_admin_head () {
  
  if ( isset($_GET['page']) && $_GET['page'] == 'comments-emails' ) {

?>
  <style type="text/css">
    #col-right.evc {
      width: 35%;
    }
    #col-left.evc {
      width: 64%;
    }    
    .evc-box{
      padding:0 20px 0 40px;
    }
    .evc-boxx {
      background: none repeat scroll 0 0 #FFFFFF;
      border-left: 4px solid #2EA2CC;
      box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);
      margin: 5px 0 15px;
      padding: 1px 12px;
    }
    .evc-boxx h3 {
      line-height: 1.5;
    }
    .evc-boxx p {
      margin: 0.5em 0;
      padding: 2px;
    }
  </style> 
  <script type="text/javascript" >  
  jQuery(document).ready(function($) {
    
    if ($(".evc-box").length) {
    
      $("#col-right").stick_in_parent({
        parent: '#col-container',
        offset_top: $('#wpadminbar').height() + 10,
      });
    }
  });
  </script>
<?php
  }
}    

function ce_ad () {

  echo '
    <div class = "evc-boxx">
      <p>'.__('Comments Emails plugin <a href = "http://ukraya.ru/comments-emails/support" target = "_blank">Support</a>', 'comments-emails') . '</p>
    </div>';
}    

add_action('admin_init', 'ce_admin_init'); 
function ce_admin_init () { 
  if ( isset($_GET['page']) && $_GET['page'] == 'comments-emails' ) {
    wp_enqueue_script('sticky-kit', plugins_url('js/jquery.sticky-kit.min.js' , __FILE__), array('jquery'), null, false); 
  }
}
