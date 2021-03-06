<?php


if(!empty($warnmessage))
{
    echo '<div data-alert class="alert-box warning">'.$warnmessage.'</div>';
 
}
?>
<div class="small-12 columns text-right"><button class="button" type="button" data-open="notificationaddmodal"><?php echo lang('rr_add');?></button></div>
<?php
  if(count($rows)>1)
  {
   $tmpl = array('table_open' => '<table id="detailsnosort" class="zebra">');
   $this->table->set_template($tmpl);
   echo '<div class="small-12 columns">';
   echo $this->table->generate($rows); 
   echo '</div>';
   $this->table->clear();
  }

/**
 * update form
 */
   $rrs = array('id'=>'notificationupdateform');

   echo '<div id="notificationupdatemodal" class="reveal small" data-reveal>';
   echo form_open(base_url().'notification/subscriber/updatestatus/',$rrs);
   echo form_input(array('name'=>'noteid','id'=>'noteid','type'=>'hidden','value'=>''));
$btns = array(
    '<button type="reset" name="cancel" value="cancel" class="button alert" data-close>' . lang('rr_cancel') . '</button>',
    '<button type="submit" name="updstatus" class="button">'. lang('btnupdate').'</button>'
);
   ?>
      <div class="header row">
      <h3><?php echo lang('updnotifstatus'); ?></h3>
      </div>
      <div></div>
      <p class="message"></p>
     <div class="row">
      <?php
       echo form_dropdown('status', $statusdropdown,set_value('status'));
     ?>
    </div>
      <div class="row">
          <?php
          echo revealBtnsRow($btns);
          ?>

     </div>
   <?php
   echo form_close();
   echo closeModalIcon();
   echo '</div>';

/**
 * add form
 */
   $rrs = array('id'=>'notificationaddform');

   echo '<div id="notificationaddmodal" class="reveal" data-reveal>';
   echo form_open(base_url().'notifications/subscriber/add/'.$encodeduser.'',$rrs);
$btns = array(
     '<button type="reset" name="cancel" value="cancel" class="button alert" data-close>' . lang('rr_cancel') . '</button>',
    '<button type="submit" class="button">'. lang('rr_add').'</button>'
);
   ?>
      <div class="header">
      <h3><?php echo lang('registerfornotification'); ?></h3>
      </div>
      <div class="help"><?php echo lang('rhelp_addnotification'); ?></div>
      <p class="message"></p>
     <div>
      <?php
       $this->load->helper('shortcodes');
       $codes = notificationCodes();
       $typedropdown[''] = lang('rr_pleaseselect');
       foreach($codes as $k=>$v)
       {
         $typedropdown[''.$v['group'].''][$k] = lang(''.$v['desclang'].'');
       }
       echo form_fieldset();
       echo '<ul class="no-bullet">';
       echo '<li>'. form_label(lang('whennotifyme'),'type');
       echo form_dropdown('type', $typedropdown,'','id="type" class="smallselect"'). '</li>';

       echo '<li>'. form_label(lang('rr_provider'),'sprovider');
       echo form_dropdown('sprovider', array(),'','id="sprovider" class="select2"').'</li>';

       echo '<li>'.form_label(lang('rr_federation'),'sfederation');
       echo form_dropdown('sfederation', array(),'','id="sfederation"').'</li>';
 
       echo '<li>'.form_label(''.lang('rr_altemail').' ('.lang('rr_optional').')','semail');
       echo '<input type="text" id="semail" name="semail" value=""/></li>';
   
       echo '</ul>';
       echo form_fieldset_close();
     ?>
    </div>
      <div class="row">
          <?php
          echo revealBtnsRow($btns);
          ?>

     </div>
   <?php
   echo form_close();
   echo' <a class="close-button" data-close>&#215;</a>';
   echo '</div>';

