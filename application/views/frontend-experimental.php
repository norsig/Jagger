<?php
$loggedin = $this->j_auth->logged_in();
$isAdministrator = FALSE;
if($loggedin)
{
   $isAdministrator = (boolean) $this->j_auth->isAdministrator();
}

$langs = array(
   'en' => array('path'=>'english','val'=>'english'),
   'cs' => array('path'=>'cs','val'=>'čeština'),
   'es' => array('path'=>'es','val'=>'español'),
   'fr-ca' => array('path'=>'fr-ca','val'=>'français'),
  'it' => array('path'=>'it','val'=>'italiano'),
    'lt' => array('path'=>'lt','val'=>'lietuvos'),
     'pl' => array('path'=>'pl','val'=>'polski'),
    'pt' => array('path'=>'pt','val'=>'português'),
  );



$pageTitle = $this->config->item('pageTitlePref');
$colorTheme = $this->config->item('colortheme');
if(empty($colorTheme))
{
   $colorTheme = 'default';
}
$base_url = base_url();
$pageTitle .= $this->title;

$args['langs'] = $langs;
$args['base_url'] = $base_url;

if(!empty($inqueue))
{
    $args['inqueue'] = $inqueue;
}

$args['isAdministrator'] = $isAdministrator;
$args['loggedin'] = $loggedin;

$foundation = $base_url.'foundation/'; 
$jquerybubblepopupthemes = $base_url.'styles/jquerybubblepopup-themes';
?>
<!DOCTYPE html>
<!--[if lt IE 7]> <html lang="<?php echo MY_Controller::getLang(); ?>" class="no-js ie6 oldie"> <![endif]-->
<!--[if IE 7]>    <html lang="<?php echo MY_Controller::getLang(); ?>" class="no-js ie7 oldie"> <![endif]-->
<!--[if IE 8]>    <html lang="<?php echo MY_Controller::getLang(); ?>" class="no-js ie8 oldie"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class='no-js' lang='<?php echo MY_Controller::getLang(); ?>'>
    <!--<![endif]-->
    <head>     
        <meta charset="utf-8">
        <meta content='IE=edge,chrome=1' http-equiv='X-UA-Compatible'>
        <?php
        echo '<title>' . $pageTitle . '</title>';
        ?>
        <meta content='rr' name='description'>
        <meta content='' name='author'>
        <meta content='width=device-width, initial-scale=1.0, user-scalable=0' name='viewport'>
        <link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>styles/jquery-ui.css"/>
        <?php
        
        //echo '<link rel="stylesheet" type="text/css" href="' . $base_url . 'styles/'.$colorTheme.'.css" />';
        echo '<link rel="stylesheet" type="text/css" href="' . $base_url . 'styles/jquery.jqplot.min.css" />';
        echo '<link rel="stylesheet" type="text/css" href="' . $base_url . 'styles/jquery-bubble-popup-v3.css" />';     
        echo '<link rel="stylesheet" type="text/css" href="' . $base_url . 'styles/idpselect.css" />';
        echo '<link rel="stylesheet" href="'.$foundation.'css/foundation.css" />';
       // echo '<link rel="stylesheet" type="text/css" href="' . $base_url . 'styles/'.$colorTheme.'.css" />';
        echo '<script src="' . $foundation . 'js/vendor/modernizr.js"></script>';

        
        ?>

    </head>
    <body class="clearfix">
        <!--[if lt IE 7]>
                    <p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
                <![endif]-->
        <?php
        $iscookieconsent = $this->rrpreference->getPreferences('cookieConsent');
        if(isset($iscookieconsent['status']) && (boolean) $iscookieconsent['status'] === TRUE && isset($iscookieconsent['value']))
        {
            $this->load->helper('cookie');
            $cookieaccepted = get_cookie('cookieAccept');
            if(empty($cookieaccepted) or $cookieaccepted != 'accepted')
            {
                $this->load->view('cookiesconsent', $iscookieconsent);
            }
        }
        if (!empty($headerjs))
        {
            echo $headerjs;
        }
        if (!empty($headermap))
        {
            echo $headermap;
        }


$this->load->view('toppanel',$args);

        ?>



        <div id="container" class="row">
            <div class="header-container">
                <header class="wrapper clearfix" role="banner">
                    <div class="header-top clearfix" style="text-align: right;">
                        <?php
                        if (!empty($provider_logo_url))
                        {
                            echo '<img src="' . $provider_logo_url . '" class="providerlogo" />';
                        }
                        ?>
                    </div>

                    <?php
                    if ($loggedin)
                    {
           $showhelp = $this->session->userdata('showhelp');
           if(!empty($showhelp) && $showhelp === TRUE)
           {
              echo '<a href="'.base_url().'ajax/showhelpstatus" id="showhelps" class="helpactive"><img src="'.base_url().'images/icons/info.png" class="iconhelpshow" style="display:none"><img src="'.base_url().'images/icons/info.png" class="iconhelpcross"></a>';
           }
           else
           {
              echo '<a href="'.base_url().'ajax/showhelpstatus" id="showhelps" class="helpinactive"><img src="'.base_url().'images/icons/info.png" class="iconhelpshow"><img src="'.base_url().'images/icons/info.png" class="iconhelpcross" style="display:none"></a>';
           }
                        ?>
                        <!-- menu -->
                        <!-- end menu -->
                        <?php
                        //$this->load->view('topbar',$args);
                    }
                    ?>
                </header>
            </div>
            <article role="main" class="clearfix">
                <?php
                $height100 = '';
                if (!empty($loadGoogleMap))
                {
                    $height100 = ' style="height: 100%" ';
                }
                ?>
                <div   <?php echo $height100 ?>>

                    <div id="wrapper"   <?php echo $height100 ?> >
                            <?php
                            if(!$loggedin)
                            {
                                $datalogin = array();
                                if(!empty($showloginform))
                                {
                                   $datalogin['showloginform'] = $showloginform;
                                   $this->load->view('auth/login',$datalogin);
                                }
                                else
                                {
                                      $this->load->view('auth/login');
                                 }

                            }
                            $this->load->view($content_view);
                            ?>
                       
                    </div>
                    <div id="navigation">
                        <?php
                        if (!empty($navigation_view))
                        {
                            $this->load->view($navigation_view);
                        }
                        ?>
                    </div>
                    <div id="extra">
                        <?php
                        if (!empty($extra_view))
                        {
                            $this->load->view($extra_view);
                        }
                        ?>
                    </div>

                </div>
            </article>
            <div id="inpre_footer"></div>
        </div>

            <div id="footer">

                <footer>
                    <?php
                    $footer = $this->rrpreference->getPreferences('pageFooter');
                    if(isset($footer['status']) && (boolean) $footer['status'] === TRUE && isset($footer['value']))
                    {
                          echo '<small>'.$footer['value'].'</small><br />';
                    }
                    $disp_mem = $this->rrpreference->getPreferences('rr_display_memory_usage');
                    if (isset($disp_mem['status']) && (boolean) $disp_mem['status'] === TRUE )
                    {
                        echo echo_memory_usage();
                    }
                    ?>

                </footer>
            </div>

        <div style="height: 50px;"></div>
        <div id="spinner" class="spinner" style="display:none;">
            <img id="img-spinner" src="<?php echo $base_url; ?>images/spinner1.gif" alt="<?php echo lang('loading');?>"/>
        </div>
        <div style="display: none">
             <input type="hidden" name="baseurl" value="<?php echo base_url(); ?>">
             <input type="hidden" name="csrfname" value="<?php echo $this->security->get_csrf_token_name(); ?>">
             <input type="hidden" name="csrfhash" value="<?php echo $this->security->get_csrf_hash(); ?>">
        </div>
        
        <button id="jquerybubblepopupthemes" style="display:none;" value="<?php echo $jquerybubblepopupthemes; ?>"></button> 

        <script src="<?php echo $foundation;?>js/vendor/jquery.js"></script>
        <script src="<?php echo $foundation;?>js/vendor/jquery-ui-1.10.4.custom.min.js"></script>

        <script type="text/javascript" src="<?php echo $base_url; ?>js/jquery.uitablefilter.js"></script>
        <?php
        echo '<script type="text/javascript" src="' . $base_url . 'js/jquery.jqplot.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jqplot.dateAxisRenderer.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jqplot.cursor.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jqplot.highlighter.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jquery.tablesorter.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jquery.inputfocus-0.9.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jquery-bubble-popup-v3.min.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/jquery.simplemodal.js"></script>';
        echo '<script type="text/javascript" src="' . $base_url . 'js/locals-v3.js"></script>';
        if (!empty($load_matrix_js))
        {
            echo '<script type="text/javascript">';
            $this->load->view('reports/matrixsp_js_view');
            echo '</script>';
        }
        ?>

        <script src="<?php echo $foundation;?>js/foundation.min.js"></script>
        <script src="<?php echo $foundation;?>js/foundation/foundation.topbar.js"></script>
        <script src="<?php echo $foundation;?>js/foundation/foundation.tab.js"></script>
    <script>
      $(document).foundation();
    </script>

        <!--[if lt IE 7]>
           <script src='//ajax.googleapis.com/ajax/libs/chrome-frame/1.0.3/CFInstall.min.js'></script>
           <script>
             window.attachEvent('onload',function(){CFInstall.check({mode:'overlay'})});
           </script>
           <![endif]-->
    </body>
</html>
