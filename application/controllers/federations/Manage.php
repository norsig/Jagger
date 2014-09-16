<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ResourceRegistry3
 * 
 * @package     RR3
 * @author      Middleware Team HEAnet 
 * @copyright   Copyright (c) 2012, HEAnet Limited (http://www.heanet.ie)
 * @license     MIT http://www.opensource.org/licenses/mit-license.php
 *  
 */
/**
 * Manage Class
 * 
 * @package     RR3
 * @author      Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 */

/**
 * @todo add permission to check for public or private perms
 */
class Manage extends MY_Controller
{

    private $tmp_providers;

    function __construct()
    {
        parent::__construct();
        $this->current_site = current_url();
        $this->load->helper(array('cert'));
        $this->session->set_userdata(array('currentMenu' => 'federation'));
        /**
         * @todo add check loggedin
         */
        $this->tmp_providers = new models\Providers;
        MY_Controller::$menuactive = 'fed';
    }

    function index()
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        else {
            $this->load->library('zacl');
            $this->title = lang('title_fedlist');
            $resource = 'fed_list';
            $federationCategories = $this->em->getRepository("models\FederationCategory")->findAll();
            $data['categories'] = array();
            foreach ($federationCategories as $v) {
                $data['categories'][] = array('catid' => '' . $v->getId() . '',
                    'name' => '' . $v->getName() . '',
                    'title' => '' . $v->getFullName() . '',
                    'desc' => '' . $v->getDescription() . '',
                    'default' => '' . $v->isDefault() . '');
            }
            $data['titlepage'] = lang('rr_federation_list');
            $data['content_view'] = 'federation/list_view.php';
            $this->load->view('page', $data);
        }
    }

    function changestatus()
    {
        if(!$this->input->is_ajax_request() || !$this->j_auth->logged_in()) 
        {
            set_status_header(403);
            echo 'access denied';
            return;
        }
        $status = trim($this->input->post('status'));
        $fedname = trim($this->input->post('fedname')); 
        if(empty($status) || empty($fedname))
        {
            set_status_header(403);
            echo 'missing arguments';
            return;
        }
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => ''.htmlspecialchars(base64url_decode($fedname)).''));
        if(empty($federation))
        {
            set_status_header(404);
            echo 'Federarion not found';
            return;
        }
        $this->load->library('zacl');
        $has_manage_access =$this->zacl->check_acl('f_' . $federation->getId(), 'manage', 'federation', ''); 
        if(!$has_manage_access)
        {
            set_status_header(403);
            echo 'Access denied';
            return;
        }
        $currentStatus = $federation->getActive();
        if($currentStatus && strcmp($status, 'disablefed') == 0)
        {
            $federation->setAsDisactive();
            $this->em->persist($federation);
            $this->em->flush();
            echo "deactivated";
            return;
        }
        elseif(!$currentStatus && strcmp($status, 'enablefed') == 0)
        {
            $federation->setAsActive();
            $this->em->persist($federation);
            $this->em->flush();
            echo "activated";
            return;

        }
        elseif(!$currentStatus && strcmp($status, 'delfed') == 0)
        {
            /**
             * @todo finish 
             */
           $this->load->library('Approval');
           $q = $this->approval->removeFederation($federation);
           $this->em->persist($q);
           $this->em->flush();
            echo "todelete";
            return;

        }
        set_status_header(403);
        echo "incorrect params sent";
        return;
    }

    private function _get_members($federation, $lang)
    {
        $keyprefix = getCachePrefix();
        $this->load->driver('cache', array('adapter' => 'memcached', 'key_prefix' => $keyprefix));
        $cachedid = 'fedmbrs_'.$federation->getId().'_'.$lang;
        $cachedResult = $this->cache->get($cachedid);

        if(!empty($cachedResult))
        {
           log_message('debug',__METHOD__.' retrieved fedmembers (lang:'.$lang.') from cache');
           return $cachedResult;

        }
        else
        {
           log_message('debug',__METHOD__.' no data in cache for id: '.$cachedid);
        }
        $fmembers = $federation->getActiveMembers();
        if (empty($fmembers)) {
            return array();
        }

        $membership = $federation->getMembership();
        $membersInArray = array();
        $membersInArray['definitions'] = array(
           'idps'=>lang('identityproviders'),
           'sps'=>lang('serviceproviders'),
           'both'=>lang('identityserviceproviders'),
           'preurl'=> base_url() . 'providers/detail/show/',
           'nomembers'=>lang('rr_nomembers'),

        );
            foreach($membership as $m)
            {
                $joinstate = $m->getJoinState();
                if($joinstate == 2)
                {
                   continue;
                }
                $p = $m->getProvider();
                $ptype = strtolower($p->getType());
                if($ptype === 'idp')
                {
                   $name = $p->getNameToWebInLang($lang,'idp');
                }
                else
                {
                   $name = $p->getNameToWebInLang($lang,'sp');
                }
                if(empty($name))
                {
                   $name = $p->getEntityId();
                }
                $membersInArray[''.$ptype.''][] = array(
                   'pid'=>$p->getId(),
                   'mdisabled'=>(int) $m->getIsDisabled(),
                   'mbanned' => (int) $m->getIsBanned(),
                   'entityid'=>$p->getEntityId(),
                   'pname'=>$name,
                   'penabled'=>$p->getAvailable(),
                );
            }
        if( $this->cache->save($cachedid, $membersInArray, 180))
        {
            log_message('debug',__METHOD__.' cacheid stored '.$cachedid);
        }
        return $membersInArray;

    }

    function showmembers($fedid)
    {
        if (!$this->input->is_ajax_request()) {
            set_status_header(404);
            echo 'Request not allowed';
            return;
        }
        if (!$this->j_auth->logged_in()) {
            set_status_header(403);
            echo 'access denied. invalid session';
            return;
        }
        $lang = MY_Controller::getLang();

        $this->load->library('zacl');

        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('id' => $fedid));
        if (empty($federation)) {
            set_status_header(404);
            echo 'Federarion not found';
            return;
        }

        $result = $this->_get_members($federation, $lang);
        echo json_encode($result);

    }

    function showcontactlist($fed_name, $type = NULL)
    {
        if (!$this->j_auth->logged_in()) {
            set_status_header(403);
            echo 'Access denied';
            return;
        }
        $this->load->library('zacl');

        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (empty($federation)) {
            show_error('Federation not found', 404);
            return;
        }
        $fed_members = $federation->getActiveMembers();
        $members_ids = array();
        if (!empty($type)) {
            if ($type === 'idp') {
                foreach ($fed_members as $m) {
                    $entype = $m->getType();
                    if (strcasecmp($entype, 'SP')!=0) {
                        $members_ids[] = $m->getId();
                    }
                }
            }
            elseif ($type === 'sp') {
                foreach ($fed_members as $m) {
                    $entype = $m->getType();
                    if (strcasecmp($entype, 'IDP')!=0) {
                        $members_ids[] = $m->getId();
                    }
                }
            }
            else {
                show_error(404);
            }
        }
        else {
            foreach ($fed_members as $m) {
                $members_ids[] = $m->getId();
            }
        }

        if (count($members_ids) == 0) {
            show_error(lang('error_nomembersforfed'), 404);
            return;
        }

        $contacts = $this->em->getRepository("models\Contact")->findBy(array('provider' => $members_ids));
        $cont_array = array();
        foreach ($contacts as $c) {
            $cont_array[$c->getEmail()] = $c->getFullName();
        }
        $this->output->set_content_type('text/plain');
        $result = "";
        foreach ($cont_array as $key => $value) {
            $result .= $key . "    <" . trim($value) . ">\n";
        }
        $data['contactlist'] = $result;
        $this->load->helper('download');
        $filename = 'federationcontactlist.txt';
        force_download($filename, $result, 'text/plain');
    }

    function show($fed_name)
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $result = array();
        $this->load->library('show_element');
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (empty($federation)) {
            show_error(lang('error_fednotfound'), 404);
            return;
        }
        $resource = $federation->getId();
        $group = 'federation';
        $owner = $federation->getOwner();
        $matched_owner = FALSE;
        if ($owner == $this->j_auth->current_user()) {
            $matched_owner = TRUE;
        }
        if (!empty($owner) && $matched_owner) {
            $has_read_access = TRUE;
            $has_write_access = TRUE;
        }
        else {
            $has_read_access = $this->zacl->check_acl('f_' . $resource, 'read', $group, '');
            $has_write_access = $this->zacl->check_acl('f_' . $resource, 'write', $group, '');
        }
        $has_addbulk_access = $this->zacl->check_acl('f_' . $resource, 'addbulk', $group, '');
        $has_manage_access = $this->zacl->check_acl('f_' . $resource, 'manage', $group, '');
        $can_edit = (boolean) ($has_manage_access OR $has_write_access);
        $this->title = lang('rr_federation_detail');

        if (!$has_read_access && ($federation->getPublic() === FALSE)) {
            $data['content_view'] = 'nopermission';
            $data['error'] = lang('rrerror_noperm_viewfed');
            $this->load->view('page', $data);
            return;
        }
        $data['federation_id'] = $federation->getId();
        $bookmarked = false;
        $b = $this->session->userdata('board');
        if (!empty($b) && is_array($b) && isset($b['fed'][$data['federation_id']])) {
            $bookmarked = true;
        }
        $defaultDigest = $this->config->item('signdigest');
        if(empty($defaultDigest))
        {
          $defaultDigest = 'SHA-1';
        }

        $digest = $federation->getDigest();
        if(empty($digest))
        {
          $digest = $defaultDigest;
        }
        $digestExport = $federation->getDigestExport();
        if(empty($digestExport))
        {
           $digestExport = $defaultDigest;
        }
        
        $data['bookmarked'] = $bookmarked;
        $data['federation_name'] = $federation->getName();
        $data['federation_sysname'] = $federation->getSysname();
        $data['federation_urn'] = $federation->getUrn();
        $data['federation_desc'] = $federation->getDescription();

        $data['federation_is_active'] = $federation->getActive();
        $federation_members = $federation->getMembers();
        $required_attributes = $federation->getAttributesRequirement()->getValues();


        $data['titlepage'] = lang('rr_feddetail').': '.$data['federation_name'];
        $data['meta_link'] = base_url() . 'metadata/federation/' . $data['federation_sysname'] . '/metadata.xml';
        $data['meta_link_signed'] = base_url() . 'signedmetadata/federation/' . $data['federation_sysname'] . '/metadata.xml';

        $data['metaexport_link'] = base_url() . 'metadata/federationexport/' . $data['federation_sysname'] . '/metadata.xml';
        $data['metaexport_link_signed'] = base_url() . 'signedmetadata/federationexport/' . $data['federation_sysname'] . '/metadata.xml';

        $data['content_view'] = 'federation/federation_show_view';
        if (!$can_edit) {
            $edit_link = '<img src="' . base_url() . 'images/icons/pencil-prohibition.png" title="' . lang('rr_nopermission') . '"/>';
            ;
        }
        else {
            $image_link = '<img src="' . base_url() . 'images/icons/pencil-field.png"/>';
            $edit_link = '<a href="' . base_url() . 'manage/fededit/show/' . $federation->getId() . '" class="editbutton editicon button small" title="edit">' . lang('rr_edit') . '</a>';
        }

        $data['result']['general'][] = array('data' => array('data' =>  ' ' . $edit_link, 'class' => 'text-right', 'colspan' => 2));
        if (empty($data['federation_is_active'])) {
            $data['result']['general'][] = array(
                        'data'=>array( 'data'=>'<div data-alert class="alert-box alert">'.lang('rr_fed_inactive_full').'</div>', 'class'=>'fedstatusinactive','colspan'=>2)
                );
        }
        else
        {
            $data['result']['general'][] = array(
                        'data'=>array( 'data'=>'<div data-alert class="alert-box alert">'.lang('rr_fed_inactive_full').'</div>', 'class'=>'fedstatusinactive', 'style'=>'display: none','colspan'=>2)
                );

        }
        $data['result']['general'][] = array(lang('rr_fed_name'), $federation->getName());
        $data['result']['general'][] = array(lang('fednameinmeta'), $federation->getUrn());
        $data['result']['general'][] = array(lang('rr_fed_sysname'), $federation->getSysname());
        
        $data['result']['general'][] = array(lang('rr_fed_publisher'), $federation->getPublisher());
        $data['result']['general'][] = array(lang('rr_fed_desc'), $federation->getDescription());
        $data['result']['general'][] = array(lang('rr_fed_tou'), $federation->getTou());
        $data['result']['general'][] = array(lang('rr_fedownercreator'), $federation->getOwner());
        $idp_contactlist = anchor(base_url() . 'federations/manage/showcontactlist/' . $fed_name . '/idp', lang('rr_fed_cntidps_list'));
        $sp_contactlist = anchor(base_url() . 'federations/manage/showcontactlist/' . $fed_name . '/sp', lang('rr_fed_cntisps_list'));
        $all_contactlist = anchor(base_url() . 'federations/manage/showcontactlist/' . $fed_name . '', lang('rr_fed_cnt_list'));
        $data['result']['general'][] = array(lang('rr_downcontactsintxt'), $idp_contactlist . '<br />' . $sp_contactlist . '<br />' . $all_contactlist);
        $data['result']['general'][] = array(lang('rr_timeline'), '<a href="' . base_url() . 'reports/timelines/showregistered/' . $federation->getId() . '" class="button secondary">Diagram</a>');

        $image_link = '<img src="' . base_url() . 'images/icons/pencil-field.png"/>';
        $edit_attributes_link = '<a href="' . base_url() . 'manage/attribute_requirement/fed/' . $federation->getId() . ' " class="editbutton editicon button small">' . lang('rr_edit') . ' ' . lang('rr_attributes') . '</a>';
        if (!$has_write_access) {
            $edit_attributes_link = '';
        }
        $data['result']['attrs'][] = array('data' => array('data' =>  $edit_attributes_link . '', 'class' => 'text-right', 'colspan' => 2));
        if (!$has_write_access) {
            $data['result']['attrs'][] = array('data' => array('data' => '<small><div class="notice">' . lang('rr_noperm_edit') . '</div></small>', 'colspan' => 2));
        }
        foreach ($required_attributes as $key) {
            $data['result']['attrs'][] = array($key->getAttribute()->getName(), $key->getStatus() . "<br /><i>(" . $key->getReason() . ")</i>");
        }
        $data['result']['membership'][] = array('data' => array('data' => lang('rr_membermanagement'), 'class' => 'highlight', 'colspan' => 2));
        if (!$has_addbulk_access) {
            $data['result']['membership'][] = array('data' => array('data' => '<small><div class="notice">' . lang('rr_noperm_bulks') . '</div></small>', 'colspan' => 2));
        }
        else {
            $data['result']['membership'][] = array('IDPs', lang('rr_addnewidpsnoinv') . anchor(base_url() . 'federations/manage/addbulk/' . $fed_name . '/idp', '<img src="' . base_url() . 'images/icons/arrow.png"/>'));

            $data['result']['membership'][] = array('SPs', lang('rr_addnewspsnoinv') . anchor(base_url() . 'federations/manage/addbulk/' . $fed_name . '/sp', '<img src="' . base_url() . 'images/icons/arrow.png"/>'));
        }
        if ($has_write_access) {
            $data['result']['membership'][] = array(lang('rr_fedinvitation'), lang('rr_fedinvidpsp') . anchor(base_url() . 'federations/manage/inviteprovider/' . $fed_name . '', '<img src="' . base_url() . 'images/icons/arrow.png"/>'));
            $data['result']['membership'][] = array(lang('rr_fedrmmember'), lang('rr_fedrmidpsp') . anchor(base_url() . 'federations/manage/removeprovider/' . $fed_name . '', '<img src="' . base_url() . 'images/icons/arrow.png"/>'));
        }
        else {
            $data['result']['membership'][] = array('data' => array('data' => '<small><div class="notice">' . lang('rr_noperm_invmembers') . '</div></small>', 'colspan' => 2));
        }



        if ($has_manage_access) {
            $data['result']['management'][] = array('data' => array('data' => lang('access_mngmt') . anchor(base_url() . 'manage/access_manage/federation/' . $resource, '<img src="' . base_url() . 'images/icons/arrow.png"/>'), 'colspan' => 2));
            $data['hiddenspan'] =  '<span id="fednameencoded" style="display:none">'.$fed_name.'</span>';
            if($federation->getActive())
            {
                $b = '<button type="button" name="fedstatus" value="disablefed" class="resetbutton reseticon alert small" title="'. lang('btn_deactivatefed').': ' .$federation->getName().'">'.lang('btn_deactivatefed').'</button>';
                $data['result']['management'][] = array('data' => array('data' => ''.$b.'', 'colspan' => 2));
                $b = '<br /><button type="button" name="fedstatus" value="enablefed" class="savebutton staricon small" style="display:none">'.lang('btn_activatefed').'</button>';
                $data['result']['management'][] = array('data' => array('data' => ''.$b.'', 'colspan' => 2));
                $b = '<br /><button type="button" name="fedstatus"  value="delfed" class="resetbutton reseticon alert small" style="display: none" title="'. lang('btn_applytodelfed').': ' .$federation->getName().'">'.lang('btn_applytodelfed').'</button>';
                $data['result']['management'][] = array('data' => array('data' => ''.$b.'', 'colspan' => 2));
            }
            else
            {
                $b = '<button type="button" name="fedstatus" value="disablefed" class="resetbutton reseticon alert small" style="display: none" title="'. lang('btn_deactivatefed').': ' .$federation->getName().'">'.lang('btn_deactivatefed').'</button>';
                $b .= '<br /><button type="button" name="fedstatus" value="enablefed" class="savebutton staricon small">'.lang('btn_activatefed').'</button>';
                $data['result']['management'][] = array('data' => array('data' => ''.$b.'', 'colspan' => 2));
                $b = '<button type="button" name="fedstatus"  value="delfed" class="resetbutton reseticon alert small" title="'. lang('btn_applytodelfed').': ' .$federation->getName().'">'.lang('btn_applytodelfed').'</button>';
                $data['result']['management'][] = array('data' => array('data' => ''.$b.'', 'colspan' => 2));
            }
        }
        else {
            $data['result']['management'][] = array('data' => array('data' => '<small><div data-alert class="alert-box warning">' . lang('rr_noperm_accessmngt') . '</div></small>', 'colspan' => 2));
        }

        if ($federation->getAttrsInmeta()) {
            $data['result']['metadata'][] = array('data' => array('data' => '<div data-alert class="alert-box warning">'.lang('rr_meta_with_attr').'</div>', 'class' => '', 'colspan' => 2));
        }
        else {
            $data['result']['metadata'][] = array('data' => array('data' => '<div data-alert class="alert-box warning">'.lang('rr_meta_with_noattr').'</div>', 'class' => '', 'colspan' => 2));
        }

        if (empty($data['federation_is_active'])) {
            $data['result']['metadata'][] = array(lang('rr_fedmetaunsingedlink'), '<span class="lbl lbl-disabled fedstatusinactive">' . lang('rr_fed_inactive') . '</span> ' . anchor($data['meta_link']));
            $data['result']['metadata'][] = array(lang('rr_fedmetasingedlink'), '<span class="lbl lbl-disabled fedstatusinactive">' . lang('rr_fed_inactive') . '</span> ' . anchor($data['meta_link_signed']));
        }
        else {
           

            $membersInArray = array('idp'=>array(),'sp'=>array(),'both'=>array());
            $lang = MY_Controller::getLang();
          
            $membersInArray2 = $this->_get_members($federation, $lang); 
            $membersInArray = array_merge($membersInArray,$membersInArray2);

            $IDPmembersInArrayToHtml = $this->show_element->MembersToHtml($membersInArray['idp']);
            $SPmembersInArrayToHtml = $this->show_element->MembersToHtml($membersInArray['sp']);
            $BOTHmembersInArrayToHtml = $this->show_element->MembersToHtml($membersInArray['both']);
            $data['result']['metadata'][] = array(lang('rr_fedmetaunsingedlink'), $data['meta_link'] . " " . anchor($data['meta_link'], '<img src="' . base_url() . 'images/icons/arrow.png"/>','class="showmetadata"'));

            $data['result']['metadata'][] = array(lang('rr_fedmetasingedlink').' <span class="label">'.$digest.'</span>', $data['meta_link_signed'] . " " . anchor_popup($data['meta_link_signed'], '<img src="' . base_url() . 'images/icons/arrow.png"/>'));

            $lexportenabled = $federation->getLocalExport();
            if ($lexportenabled === TRUE) {
                $data['result']['metadata'][] = array(lang('rr_fedmetaexportunsingedlink'), $data['metaexport_link'] . " " . anchor_popup($data['metaexport_link'], '<img src="' . base_url() . 'images/icons/arrow.png"/>','class="showmetadata"'));
                $data['result']['metadata'][] = array(lang('rr_fedmetaexportsingedlink').' <span class="label">'.$digestExport.'</span>', $data['metaexport_link_signed'] . " " . anchor_popup($data['metaexport_link_signed'], '<img src="' . base_url() . 'images/icons/arrow.png"/>'));
            }

            $gearmanenabled = $this->config->item('gearman');
            if ($has_write_access && !empty($gearmanenabled)) {
                $data['result']['metadata'][] = array('' . lang('signmetadata') . showBubbleHelp(lang('rhelp_signmetadata')) . '', '<a href="' . base_url() . 'msigner/signer/federation/' . $federation->getId() . '" id="fedmetasigner"/><button type="button" class="savebutton staricon tiny">' . lang('btn_signmetadata') . '</button></a>', '');
            }

            $data['result']['membership'][] = array('data' => array('data' => lang('identityprovidersmembers'), 'class' => 'highlight', 'colspan' => 2));
            $data['result']['membership'][] = array('data' => array('data' => $IDPmembersInArrayToHtml, 'colspan' => 2));
            $data['result']['membership'][] = array('data' => array('data' => lang('serviceprovidersmembers'), 'class' => 'highlight', 'colspan' => 2));
            $data['result']['membership'][] = array('data' => array('data' => $SPmembersInArrayToHtml, 'colspan' => 2));
            $data['result']['membership'][] = array('data' => array('data' => lang('bothprovidersmembers'), 'class' => 'highlight', 'colspan' => 2));
            $data['result']['membership'][] = array('data' => array('data' => $BOTHmembersInArrayToHtml, 'colspan' => 2));
        }

        $fvalidators = $federation->getValidators();

        if ($has_write_access) {
            $data['fvalidator'] = TRUE;
            $data['result']['fvalidators'] = array();
            $addbtn = '<a href="' . base_url() . 'manage/fvalidatoredit/vedit/' . $federation->getId() . '" class="button small">' . lang('rr_add') . '</a>';
            $data['result']['fvalidators'][] = array('data' => array('data' => $addbtn, 'class' => 'text-right', 'colspan' => 2));
        }
        if ($fvalidators->count() > 0) {
            
            if ($has_write_access) {
                $fvdata = '<dl class="accordion" data-accordion>';
                foreach ($fvalidators as $f) {
                    $d['fvalidators'] = array();
                    $fvdata .=' <dd class="accordion-navigation">';
                    $fvdata .='<a href="#fvdata'.$f->getId().'">'.$f->getName().'</a>';
                    $fvdata .= '<div id="fvdata'.$f->getId().'" class="content">';
                    $editbtn = '<a href="' . base_url() . 'manage/fvalidatoredit/vedit/' . $federation->getId() . '/' . $f->getId() . '" class="editbutton editicon right button small">' . lang('rr_edit') . '</a>';

                    $d['fvalidators'][] = array('data' => array('data' =>  ' ' . $editbtn, 'class' => '', 'colspan' => 2));
                    $isenabled = $f->getEnabled();
                    $ismandatory = $f->getMandatory();
                    $method = $f->getMethod();
                    if ($isenabled) {
                        $d['fvalidators'][] = array('data' => array('data' => makeLabel('active', lang('lbl_enabled'), lang('lbl_enabled')), 'colspan' => 2));
                    }
                    else {
                        $d['fvalidators'][] = array('data' => array('data' => makeLabel('disabled', lang('lbl_disabled'), lang('lbl_disabled')), 'colspan' => 2));
                    }
                    if ($ismandatory) {
                        $d['fvalidators'][] = array('data' => array('data' => makeLabel('active', lang('lbl_mandatory'), lang('lbl_mandatory')), 'colspan' => 2));
                    }
                    else {
                        $d['fvalidators'][] = array('data' => array('data' => makeLabel('disabled', lang('lbl_optional'), lang('lbl_optional')), 'colspan' => 2));
                    }
                    $d['fvalidators'][] = array('data' => lang('Description'), 'value' => $f->getDescription());
                    $d['fvalidators'][] = array('data' => lang('fvalid_doctype'), 'value' => $f->getDocutmentType());
                    $d['fvalidators'][] = array('data' => lang('fvalid_url'), 'value' => $f->getUrl());
                    $d['fvalidators'][] = array('data' => lang('rr_httpmethod'), 'value' => $method);
                    $d['fvalidators'][] = array('data' => lang('fvalid_entparam'), 'value' => $f->getEntityParam());
                    $optargs1 = $f->getOptargs();
                    $optargsStr = array();
                    foreach($optargs1 as $k=>$v)
                    {
                           if($v === null)
                           {
                              $optargsStr[] = $k;
                           }
                           else
                           {
                              $optargsStr[] = $k.'='.$v;
                           }
                    }
                    $d['fvalidators'][] = array('data' => lang('fvalid_optargs'), 'value' => implode('<br />', $optargsStr));
                    if (strcmp($method, 'GET') == 0) {
                        $d['fvalidators'][] = array('data' => lang('rr_argsep'), 'value' => $f->getSeparator());
                    }
                    else {
                    }
                    $d['fvalidators'][] = array('data' => lang('fvalid_retelements'), 'value' => implode('<br />', $f->getReturnCodeElement()));

                    $retvalues = $f->getReturnCodeValues();
                    $retvaluesToHtml = '';
                    foreach ($retvalues as $k => $v) {
                        $retvaluesToHtml .= '<div>' . $k . ': ';
                        if (!empty($v) && is_array($v)) {
                            foreach ($v as $v1) {
                                $retvaluesToHtml .= '"' . $v1 . '"; ';
                            }
                        }
                    }
                    $d['fvalidators'][] = array('data' => lang('fvalid_retelements'), 'value' => $retvaluesToHtml);
                    $d['fvalidators'][] = array('data' => lang('fvalid_msgelements'), 'value' => implode('<br />', $f->getMessageCodeElements()));
                    $fvdata .= $this->table->generate($d['fvalidators']);
                    $fvdata .='</div>';
                    $fvdata .= '</dd>';
                   
                }
                $fvdata .='</dl>';
                $data['result']['fvalidators'][] = array('data'=>array('data'=>$fvdata,'colspan'=>2,'class'=>''));
            }
            else {
                $data['result']['fvalidators'][] = array('data' => array('data' => '<div class="alert">' . lang('rr_noperm') . '</div>', 'colspan' => 2));
            }
        }

        $this->load->view('page', $data);
    }

    function members($fed_name)
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (empty($federation)) {
            show_error(lang('error_fednotfound'), 404);
        }
        $resource = $federation->getId();
        $action = 'read';
        $group = 'federation';
        $has_read_access = $this->zacl->check_acl($resource, $action, $group, '');
        if (!$has_read_access) {
            $data['content_view'] = 'nopermission';
            $data['error'] = lang('rerror_nopermfedlistview');
            $this->load->view('page', $data);
            return;
        }
        $data['federation_name'] = $federation->getName();
        $data['metadata_link'] = base_url() . "metadata/federation/" . base64url_encode($data['federation_name']);
        $members = $federation->getMembers();
        $i = 0;
        foreach ($members as $m) {
            $id = $m->getId();
            $link = base_url() . 'providers/detail/show/' . $id;
            $data['m_list'][$i]['name'] = $m->getName();
            $data['m_list'][$i]['entity'] = $m->getEntityId();
            $data['m_list'][$i++]['link'] = anchor($link, '&gt;&gt');
        }
        $this->title = lang('rr_fedmembers');
        $data['content_view'] = 'federation/federation_members_view';
        $this->load->view('page', $data);
    }

    function addbulk($fed_name, $type, $message = null)
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $form_elements = array();

        $this->load->helper('form');
        if ($type === 'idp') {
            $this->load->library('show_element');
            $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
            if (empty($federation)) {
                show_error(lang('error_fednotfound'), 404);
            }
            $resource = $federation->getId();
            $action = 'addbulk';
            $group = 'federation';
            $has_addbulk_access = $this->zacl->check_acl($resource, $action, $group, '');
            if (!$has_addbulk_access) {
                $data['content_view'] = 'nopermission';
                $data['error'] = lang('rr_noperm');
                $this->load->view('page', $data);
                return;
            }
            $data['federation_name'] = $federation->getName();
            $data['federation_urn'] = $federation->getUrn();
            $data['federation_desc'] = $federation->getDescription();

            $data['federation_is_active'] = $federation->getActive();
            $federation_members = $federation->getMembers();
            $providers = $this->tmp_providers->getIdps();
            $memberstype = 'idp';
            $data['memberstype'] = $memberstype;
            $data['subtitlepage'] = lang('rr_addnewidpsnoinv');
        }
        elseif ($type === 'sp') {
            $this->load->library('show_element');
            $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
            if (empty($federation)) {
                show_error(lang('error_fednotfound'), 404);
            }
            $data['federation_name'] = $federation->getName();
            $data['federation_urn'] = $federation->getUrn();
            $data['federation_desc'] = $federation->getDescription();

            $data['federation_is_active'] = $federation->getActive();
            $federation_members = $federation->getMembers();
            $providers = $this->tmp_providers->getSps();
            $data['memberstype'] = 'sp';
            $data['subtitlepage'] = lang('rr_addnewspsnoinv');
        }
        else {
            log_message('error', 'type is expected to be sp or idp but ' . $type . 'given');
            show_error('wrong type', 404);
        }
        foreach ($providers as $i) {
            if (!$federation_members->contains($i)) {
                $checkbox = array(
                    'id' => 'member[' . $i->getId() . ']',
                    'name' => 'member[' . $i->getId() . ']',
                    'value' => 1,);
                $form_elements[] = array(
                    'name' => $i->getName() . ' (' . $i->getEntityId() . ')',
                    'box' => form_checkbox($checkbox),
                );
            }
        }
        $data['content_view'] = 'federation/bulkadd_view';
        $data['form_elements'] = $form_elements;
        $data['fed_encoded'] = $fed_name;
        $data['message'] = $message;
        $data['titlepage'] = lang('rr_federation').': <a href="'.base_url().'federations/manage/show/'.$data['fed_encoded'].'">'.$federation->getName().'</a>';
        $this->load->view('page', $data);
    }

    public function bulkaddsubmit()
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $message = null;
        $fed_name = $this->input->post('fed');
        $memberstype = $this->input->post('memberstype');
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (!empty($federation)) {
            $existingMembers = $federation->getMembershipProviders();
            $membership = $federation->getMembership();
            

            $m = $this->input->post('member');
            if (!empty($m) && is_array($m) && count($m) > 0) {
                $m_keys = array_keys($m);
                if ($memberstype === 'idp') {
                    $new_members = $this->em->getRepository("models\Provider")->findBy(array('type' => array('IDP', 'BOTH'), 'id' => $m_keys));
                }
                elseif ($memberstype === 'sp') {
                    $new_members = $this->em->getRepository("models\Provider")->findBy(array('type' => array('SP', 'BOTH'), 'id' => $m_keys));
                }
                else {
                    log_message('error', 'missed or wrong membertype while adding new members to federation');
                    show_error('Missed members type', 503);
                }
                $newMembersArray = array();
                foreach ($new_members as $nmember) {
                    if (!$existingMembers->contains($nmember)) {
                        $newMembersArray[] = $nmember->getEntityId();
                        $newMembership  = new models\FederationMembers();
                        $newMembership->setProvider($nmember);
                        $newMembership->setFederation($federation);
                        if($nmember->getLocal())
                        {
                           $newMembership->setJoinState('1');
                        }
                        $this->em->persist($newMembership);
                    }
                    else
                    {
                         $doFilter = array(''.$federation->getId().'');
                         $m1 = $nmember->getMembership()->filter(
                              function($entry) use($doFilter){
                           return (in_array($entry->getFederation()->getId(), $doFilter));
                           }
                        );
                          if(!empty($m1))
                          {
                             foreach($m1 as $v1)
                             {
                                if($nmember->getLocal())
                                {
                                   $v1->setJoinState('1');
                                }
                                else
                                {
                                   $v1->setJoinState('0');
                                }
                                $this->em->persist($v1);
                                $newMembersArray[] = $nmember->getEntityId();
                             }
                          }

                    }
                }
                if (count($newMembersArray) > 0) {
                    $subject = 'Members of Federations changed';
                    $body = 'Dear user' . PHP_EOL;
                    $body .= 'Federation ' . $federation->getName() . ' has new members:' . PHP_EOL;
                    $body .= implode(';' . PHP_EOL, $newMembersArray);
                    $this->email_sender->addToMailQueue(array('gfedmemberschanged', 'fedmemberschanged'), $federation, $subject, $body, array(), false);
                }

                $this->em->flush();
                $message = '<div class="success">' . lang('rr_fedmembersadded') . '</div>';
            }
            else {
                $message = '<div class="alert">' . sprintf(lang('rr_nomemtype_selected'), $memberstype) . '</div>';
            }
        }
        else {
            show_error('federation not found', 404);
        }
        return $this->addbulk($fed_name, $memberstype, $message);
    }

    private function _invite_submitvalidate()
    {
        $this->load->library('form_validation');
        $this->form_validation->set_rules('provider', lang('rr_provider'), 'required|numeric|xss_clean');
        $this->form_validation->set_rules('message', lang('rr_message'), 'required|xss_clean');
        return $this->form_validation->run();
    }

    private function _remove_submitvalidate()
    {
        $this->load->library('form_validation');
        $this->form_validation->set_rules('provider', lang('rr_provider'), 'required|numeric|xss_clean');
        $this->form_validation->set_rules('message', lang('rr_message'), 'required|xss_clean');
        return $this->form_validation->run();
    }

    public function inviteprovider($fed_name)
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $this->load->library('show_element');
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (empty($federation)) {
            show_error('Federation not found', 404);
        }
        $resource = $federation->getId();
        $owner = $federation->getOwner();
        $matched_owner = FALSE;
        if ($owner == $this->j_auth->current_user()) {
            $matched_owner = TRUE;
        }
        if (!empty($owner) && $matched_owner) {
            $has_write_access = TRUE;
        }
        else {
            $has_write_access = $this->zacl->check_acl('f_' . $resource, 'write', 'federation', '');
        }
        if (!$has_write_access) {
            show_error('no access', 403);
            return;
        }
        $data['subtitle'] = lang('rr_federation') . ': ' . $federation->getName() . ' ' . anchor(base_url() . 'federations/manage/show/' . base64url_encode($federation->getName()), '<img src="' . base_url() . 'images/icons/arrow-in.png"/>');
        log_message('debug', '_________Before validation');
        if ($this->_invite_submitvalidate() === TRUE) {
            log_message('debug', 'Invitation form is valid');
            $provider_id = $this->input->post('provider');
            $message = $this->input->post('message');
            $inv_member = $this->tmp_providers->getOneById($provider_id);
            if (empty($inv_member)) {
                $data['error'] = lang('rerror_providernotexist');
            }
            else {
                $inv_member_federations = $inv_member->getFederations();
                if ($inv_member_federations->contains($federation)) {
                    $data['error'] = sprintf(lang('rr_provideralready_member_of'), $federation->getName());
                }
                else {
                    $this->load->library('approval');
                    /* create request in queue with flush */
                    $add_to_queue = $this->approval->invitationProviderToQueue($federation, $inv_member, 'Join');
                    if ($add_to_queue) {
                        $mail_recipients = array();
                        $mail_sbj = "Invitation: join federation: " . $federation->getName();
                        $mail_body = "Hi,".PHP_EOL."Just few moments ago Administator of federation \"" . $federation->getName() . "\"".PHP_EOL;
                        $mail_body .= "invited Provider: \"" . $inv_member->getName() . "(" . $inv_member->getEntityId() . ")\"".PHP_EOL;
                        $mail_body .= "to join his federation.".PHP_EOL;
                        $mail_body .= "To accept or reject this request please go to Resource Registry".PHP_EOL;
                        $mail_body .= base_url() . "reports/awaiting".PHP_EOL.PHP_EOL.PHP_EOL;
                        $mail_body .= "======= additional message attached by requestor ===========".PHP_EOL;
                        $mail_body .= $message .PHP_EOL;
                        $mail_body .= "=============================================================".PHP_EOL;


                        $this->email_sender->addToMailQueue(array('grequeststoproviders', 'requeststoproviders'), $inv_member, $mail_sbj, $mail_body, array(), false);
                    }
                }
            }
        }
        $current_members = $federation->getMembers();
        $local_providers = $this->tmp_providers->getLocalProviders();
        $list = array('IDP' => array(), 'SP' => array(), 'BOTH' => array());
        foreach ($local_providers as $l) {
            if (!$current_members->contains($l)) {
                $name = $l->getName();
                if (empty($name)) {
                    $name = $l->getEntityId();
                }
                $list[$l->getType()][$l->getId()] = $name;
            }
        }
        $list = array_filter($list);
        if (count($list) > 0) {
            $data['providers'] = $list;
        }
        else {
            $data['error_message'] = lang('rr_fednoprovidersavail');
        }
        $data['fedname'] = $federation->getName();
        $this->load->helper('form');

        $data['titlepage'] = lang('rr_federation').': <a href="'.base_url().'federations/manage/show/'.base64url_encode($federation->getName()).'">'.$federation->getName().'</a>';

        $data['content_view'] = 'federation/invite_provider_view';
        $this->load->view('page', $data);
    }

    public function removeprovider($fed_name)
    {
        if (!$this->j_auth->logged_in()) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $federation = $this->em->getRepository("models\Federation")->findOneBy(array('name' => base64url_decode($fed_name)));
        if (empty($federation)) {
            show_error('Federation not found', 404);
        }
        $resource = $federation->getId();
        $owner = $federation->getOwner();
        $matched_owner = FALSE;
        if ($owner == $this->j_auth->current_user()) {
            $matched_owner = TRUE;
        }
        if (!empty($owner) && $matched_owner) {
            $has_write_access = TRUE;
        }
        else {
            $has_write_access = $this->zacl->check_acl('f_' . $resource, 'write', 'federation', '');
        }
        if (!$has_write_access) {
            show_error('no access', 403);
            return;
        }
        log_message('debug', '_________Before validation');
        if ($this->_remove_submitvalidate() === TRUE) {
            log_message('debug', 'Remove provider from fed form is valid');
            $provider_id = $this->input->post('provider');
            $message = $this->input->post('message');
            $inv_member = $this->tmp_providers->getOneById($provider_id);
            if (empty($inv_member)) {
                $data['error_message'] = lang('rerror_providernotexist');
            }
            else {
                if ($this->config->item('rr_rm_member_from_fed') === TRUE) {
                    $p_tmp = new models\AttributeReleasePolicies;
                    $arp_fed = $p_tmp->getFedPolicyAttributesByFed($inv_member, $federation);
                    if (!empty($arp_fed) && is_array($arp_fed) && count($arp_fed) > 0) {
                        foreach ($arp_fed as $r) {
                            $this->em->remove($r);
                        }
                        $rm_arp_msg = "Also existing attribute release policy for this federation has been removed<br/>";
                        $rm_arp_msg .="It means when in the future you join this federation you will need to set attribute release policy for it again<br />";
                    }
                    else {
                        $rm_arp_msg = '';
                    }
                    $doFilter = array(''.$federation->getId().'');
                    $m2 = $inv_member->getMembership()->filter(
                      function($entry) use($doFilter){
                        return (in_array($entry->getFederation()->getId(), $doFilter));
                       }
                    );
                    foreach($m2 as $v2)
                    {
                       log_message('debug','GKS OOOO');
                       if($inv_member->getLocal())
                       {
                          $v2->setJoinState('2');
                          $this->em->persist($v2);
                       }
                       else
                       {
                         $inv_member->getMembership()->removeElement($v2);
                         $this->em->remove($v2);
                       }
                    }
                    $provider_name = $inv_member->getName();
                    if (empty($provider_name)) {
                        $provider_name = $inv_member->getEntityId();
                    }
                    $this->em->persist($inv_member);
                    $this->em->flush();
                    $spec_arps_to_remove = $p_tmp->getSpecCustomArpsToRemove($inv_member);
                    if (!empty($spec_arps_to_remove) && is_array($spec_arps_to_remove) && count($spec_arps_to_remove) > 0) {
                        foreach ($spec_arps_to_remove as $rp) {
                            $this->em->remove($rp);
                        }
                        $this->em->flush();
                    }
                    $data['success_message'] = "You just removed provider <b>" . $provider_name . "</b> from federation: <b>" . $federation->getName() . "</b><br />";
                    $data['success_message'] .= $rm_arp_msg;
                    if ($this->config->item('notify_if_provider_rm_from_fed') === TRUE) {
                        $mail_recipients = array();
                        $mail_sbj = "\"" . $provider_name . "\" has been removed from federation \"" . $federation->getName() . "\"";
                        $mail_body = "Hi,\r\nJust few moments ago Administator of federation \"" . $federation->getName() . "\"\r\n";
                        $mail_body .= "just removed " . $provider_name . " (" . $inv_member->getEntityId() . ") from federation\r\n";
                        if (!empty($message)) {
                            $mail_body .= "\r\n\r\n======= additional message attached by administrator ===========\r\n";
                            $mail_body .= $message . "\r\n";
                            $mail_body .= "================================================================\r\n";
                        }

                        $this->email_sender->addToMailQueue(array('gfedmemberschanged', 'fedmemberschanged'), $federation, $mail_sbj, $mail_body, array(), $sync = false);
                        $this->em->flush();
                    }
                }
                else {
                    log_message('error', 'rr_rm_member_from_fed is not set in config');
                    show_error('missed some config setting, Please contact with admin.', 500);
                    return;
                }
            }
        }
        $data['subtitle'] = 'Federation: ' . $federation->getName() . ' ' . anchor(base_url() . 'federations/manage/show/' . base64url_encode($federation->getName()), '<img src="' . base_url() . 'images/icons/arrow-in.png"/>');

        $current_members = $federation->getMembers();
        if ($current_members->count() > 0) {
            $list = array('IDP' => array(), 'SP' => array(), 'BOTH' => array());
            foreach ($current_members as $l) {
                $name = $l->getName();
                if (empty($name)) {
                    $name = $l->getEntityId();
                }
                $list[$l->getType()][$l->getId()] = $name;
            }
            $list = array_filter($list);
            $data['providers'] = $list;
            $data['fedname'] = $federation->getName();
        }
        else {
            $data['error_message'] = lang('error_notfoundmemberstoberm');
        }
        $this->load->helper('form');
        $data['content_view'] = 'federation/remove_provider_view';
        $this->load->view('page', $data);
    }

}
