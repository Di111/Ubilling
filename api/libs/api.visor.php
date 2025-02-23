<?php

class UbillingVisor {

    /**
     * Contains system alter.ini config as key=>value
     *
     * @var array
     */
    protected $altCfg = array();

    /**
     * Contains all stargazer user data as login=>data
     *
     * @var array
     */
    protected $allUserData = array();

    /**
     * Contains all visor users data as id=>data
     *
     * @var array
     */
    protected $allUsers = array();

    /**
     * Contains all visor cameras data as id=>data
     *
     * @var array
     */
    protected $allCams = array();

    /**
     * Contains all visor dvrs data as id=>data
     *
     * @var array
     */
    protected $allDvrs = array();

    /**
     * Contains all available users payment IDs
     *
     * @var array
     */
    protected $allPaymentIDs = array();

    /**
     * Visor charge mode from VISOR_CHARGE_MODE config option.
     *
     * @var int
     */
    protected $chargeMode = 1;

    /**
     * System messages helper object placeholder
     *
     * @var object
     */
    protected $messages = '';

    /**
     * Basic module URLs
     */
    const URL_ME = '?module=visor';
    const URL_USERS = '&users=true';
    const URL_CAMS = '&cams=true';
    const URL_USERCAMS = '&ajaxusercams=';
    const URL_ALLCAMS = '&ajaxallcams=true';
    const URL_DVRS = '&dvrs=true';
    const URL_AJUSERS = '&ajaxusers=true';
    const URL_DELUSER = '&deleteuserid=';
    const URL_DELDVR = '&deletedvrid=';
    const URL_USERVIEW = '&showuser=';
    const URL_CAMPROFILE = '?module=userprofile&username=';
    const URL_CAMVIEW = '&showcamera=';

    /**
     * Some default tables names
     */
    const TABLE_USERS = 'visor_users';
    const TABLE_CAMS = 'visor_cams';
    const TABLE_DVRS = 'visor_dvrs';

    public function __construct() {
        $this->loadConfigs();
        $this->initMessages();
        $this->loadUserData();
        $this->loadUsers();
        $this->loadPaymentIds();
        $this->loadCams();
        $this->loadDvrs();
    }

    /**
     * Loads reqired configss
     * 
     * @global object $ubillingConfig
     * 
     * @return void
     */
    protected function loadConfigs() {
        global $ubillingConfig;
        $this->altCfg = $ubillingConfig->getAlter();
        if (@$this->altCfg['VISOR_CHARGE_MODE']) {
            $this->chargeMode = $this->altCfg['VISOR_CHARGE_MODE'];
        }
    }

    /**
     * Inits system message helper for further usage
     * 
     * @return void
     */
    protected function initMessages() {
        $this->messages = new UbillingMessageHelper();
    }

    /**
     * Loads all existing users data from database
     * 
     * @return void
     */
    protected function loadUserData() {
        $this->allUserData = zb_UserGetAllDataCache();
    }

    /**
     * Loads available payment IDs from database
     * 
     * @return void
     */
    protected function loadPaymentIds() {
        if ($this->altCfg['OPENPAYZ_REALID']) {
            $query = "SELECT `realid`,`virtualid` from `op_customers`";
            $allcustomers = simple_queryall($query);
            if (!empty($allcustomers)) {
                foreach ($allcustomers as $io => $eachcustomer) {
                    $this->allPaymentIDs[$eachcustomer['realid']] = $eachcustomer['virtualid'];
                }
            }
        } else {
            if (!empty($this->allUserData)) {
                foreach ($this->allUserData as $io => $each) {
                    $this->allPaymentIDs[$each['login']] = ip2int($each['ip']);
                }
            }
        }
    }

    /**
     * Loads all visor users data into protected property
     * 
     * @return void
     */
    protected function loadUsers() {
        $query = "SELECT * from `visor_users`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allUsers[$each['id']] = $each;
            }
        }
    }

    /**
     * Loads all visor cameras data into protected property
     * 
     * @return void
     */
    protected function loadCams() {
        $query = "SELECT * from `visor_cams` ORDER BY `id` DESC";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allCams[$each['id']] = $each;
            }
        }
    }

    /**
     * Loads all visor DVR data into protected property
     * 
     * @return void
     */
    protected function loadDvrs() {
        $query = "SELECT * from `visor_dvrs`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allDvrs[$each['id']] = $each;
            }
        }
    }

    /**
     * Renders default controls panel
     * 
     * @return string
     */
    public function panel() {
        $result = '';
        $result .= wf_Link(self::URL_ME . self::URL_USERS, wf_img('skins/ukv/users.png') . ' ' . __('Users'), false, 'ubButton') . ' ';
        if (cfr('VISOREDIT')) {
            $result .= wf_modalAuto(wf_img('skins/ukv/add.png') . ' ' . __('Users registration'), __('Users registration'), $this->renderUserCreateForm(), 'ubButton') . ' ';
        }
        $result .= wf_Link(self::URL_ME . self::URL_CAMS, wf_img('skins/photostorage.png') . ' ' . __('Cams'), false, 'ubButton') . ' ';
        if (cfr('VISOREDIT')) {
            $result .= wf_Link(self::URL_ME . self::URL_DVRS, wf_img('skins/icon_restoredb.png') . ' ' . __('DVRs'), false, 'ubButton') . ' ';
        }
        return ($result);
    }

    /**
     * Renders available users list container
     * 
     * @return string
     */
    public function renderUsers() {
        $result = '';
        $opts = '"order": [[ 0, "desc" ]]';
        $columns = array('ID', 'Date', 'Name', 'Phone', 'Primary account', 'Charge', 'Cams', 'Actions');
        $result .= wf_JqDtLoader($columns, self::URL_ME . self::URL_AJUSERS, false, 'Users', 50, $opts);
        return ($result);
    }

    /**
     * Renders users datatables data
     * 
     * @return void
     */
    public function ajaxUsersList() {
        $json = new wf_JqDtHelper();
        if (!empty($this->allUsers)) {
            foreach ($this->allUsers as $io => $each) {
                $data[] = $each['id'];
                $data[] = $each['regdate'];
                $visorUserLabel = $this->iconVisorUser() . ' ' . $each['realname'];
                $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['id'], $visorUserLabel);
                $data[] = $visorUserLink;
                $data[] = $each['phone'];
                if (!empty($each['primarylogin'])) {
                    $primaryAccount = $each['primarylogin'];
                    $userAddress = @$this->allUserData[$primaryAccount]['fulladress'];
                    $primAccLink = wf_Link(self::URL_CAMPROFILE . $each['primarylogin'], web_profile_icon() . ' ' . $userAddress);
                } else {
                    $primAccLink = '';
                }


                $data[] = $primAccLink;
                $chargeFlag = ($each['chargecams']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                $data[] = $chargeFlag;
                $data[] = $this->getUserCamerasCount($each['id']);
                $actLinks = '';
                //$actLinks .= wf_JSAlert(self::URL_ME . self::URL_DELUSER . $each['id'], web_delete_icon(), $this->messages->getDeleteAlert()) . ' ';
                $actLinks .= wf_Link(self::URL_ME . self::URL_USERVIEW . $each['id'], web_edit_icon());
                $data[] = $actLinks;
                $json->addRow($data);
                unset($data);
            }
        }
        $json->getJson();
    }

    /**
     * Renders visor user creation form
     * 
     * @return string
     */
    public function renderUserCreateForm() {
        $result = '';
        $sup = wf_tag('sup') . '*' . wf_tag('sup', true);
        $inputs = wf_HiddenInput('newusercreate', 'true');
        $inputs .= wf_TextInput('newusername', __('Name') . $sup, '', true, 25);
        $inputs .= wf_TextInput('newuserphone', __('Phone'), '', true, 20, 'mobile');
        $inputs .= wf_CheckInput('newuserchargecams', __('Charge money from primary account for linked camera users if required'), true, false);
        $inputs .= wf_delimiter();
        $inputs .= wf_Submit(__('Create'));
        $result .= wf_Form('', 'POST', $inputs, 'glamour');
        return ($result);
    }

    /**
     * Creates new user in database
     * 
     * @return void
     */
    public function createUser() {
        if (wf_CheckPost(array('newusercreate', 'newusername'))) {
            $newRealName = $_POST['newusername'];
            $newRealNameF = mysql_real_escape_string($newRealName);
            $newPhone = mysql_real_escape_string($_POST['newuserphone']);
            $newChargeCams = (wf_CheckPost(array('newuserchargecams'))) ? 1 : 0;
            $date = curdatetime();
            $query = "INSERT INTO `" . self::TABLE_USERS . "` (`id`,`regdate`,`realname`,`phone`,`chargecams`,`primarylogin`) VALUES "
                    . "(NULL,'" . $date . "','" . $newRealNameF . "','" . $newPhone . "','" . $newChargeCams . "','');";
            nr_query($query);
            $newId = simple_get_lastid(self::TABLE_USERS);
            log_register('VISOR USER CREATE [' . $newId . '] NAME `' . $newRealName . '`');
        }
    }

    /**
     * Returns array of cameras associated to some user
     * 
     * @param int $userId
     * 
     * @return array
     */
    protected function getUserCameras($userId) {
        $result = array();
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['visorid'] == $userId) {
                    $result[$each['id']] = $each;
                }
            }
        }
        return ($result);
    }

    /**
     * Returns camera ID if login have camera associated
     * 
     * @param string $login
     * 
     * @return int/void
     */
    protected function getCameraIdByLogin($login) {
        $result = '';
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['login'] == $login) {
                    $result = $each['id'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Checks is some account already someones primary or not
     * 
     * @param string $userLogin
     * 
     * @return bool
     */
    protected function isPrimaryAccountFree($userLogin) {
        $result = true;
        if (!empty($userLogin)) {
            if (!empty($this->allUsers)) {
                foreach ($this->allUsers as $io => $each) {
                    if ($each['primarylogin'] == $userLogin) {
                        $result = false;
                        break;
                    }
                }
            }
        }
        return($result);
    }

    /**
     * Returns camera user assigned visor user ID if exists
     * 
     * @param string $userLogin
     * 
     * @return int/void
     */
    public function getCameraUser($userLogin) {
        $result = '';
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['login'] == $userLogin) {
                    $result = $each['visorid'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Returns userId by its associated primary account
     * 
     * @param string $userLogin
     * 
     * @return int/void
     */
    public function getPrimaryAccountUserId($userLogin) {
        $result = '';
        if (!empty($this->allUsers)) {
            foreach ($this->allUsers as $io => $each) {
                if ($each['primarylogin'] == $userLogin) {
                    $result = $each['id'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Returns count of associated user cameras
     * 
     * @param int $userId
     * 
     * @return int
     */
    protected function getUserCamerasCount($userId) {
        $result = 0;
        $userCameras = $this->getUserCameras($userId);
        if (!empty($userCameras)) {
            $result = sizeof($userCameras);
        }
        return ($result);
    }

    /**
     * Deletes user from database
     * 
     * @param int $userId
     * 
     * @return void/string on error
     */
    public function deleteUser($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $camerasCount = $this->getUserCamerasCount($userId);
            if ($camerasCount == 0) {
                $query = "DELETE from `" . self::TABLE_USERS . "` WHERE `id`='" . $userId . "';";
                nr_query($query);
                log_register('VISOR USER DELETE [' . $userId . ']');
            } else {
                $result .= __('User have some cameras associated');
            }
        } else {
            $result .= __('User not exists');
        }
        return ($result);
    }

    /**
     * Returns user primary camera controls if primary available
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserPrimaryAccount($userId) {
        $result = '';
        if (isset($this->allUsers[$userId])) {
            $userData = $this->allUsers[$userId];
            $primaryAccount = $userData['primarylogin'];
            if (!empty($primaryAccount)) {
                if (isset($this->allUserData[$primaryAccount])) {
                    $cells = wf_TableCell(__('Primary account'), '30%', 'row2');
                    $linkLabel = (@$this->allUserData[$primaryAccount]['fulladress']) ? $this->allUserData[$primaryAccount]['fulladress'] : $primaryAccount;
                    $primaLink = wf_Link(self::URL_CAMPROFILE . $primaryAccount, web_profile_icon() . ' ' . $linkLabel);
                    $cells .= wf_TableCell($primaLink);
                    $rows = wf_TableRow($cells, 'row3');

                    $cells = wf_TableCell(__('Balance'), '30%', 'row2');
                    $cells .= wf_TableCell($this->allUserData[$primaryAccount]['Cash']);
                    $rows .= wf_TableRow($cells, 'row3');

                    $cells = wf_TableCell(__('Payment ID'), '30%', 'row2');
                    $cells .= wf_TableCell($this->allPaymentIDs[$primaryAccount]);
                    $rows .= wf_TableRow($cells, 'row3');
                    $result .= $rows;
                } else {
                    $cells = wf_TableCell(__('Primary account'), '30%', 'row2');
                    $cells .= wf_TableCell(__('Not exists') . ': ' . $primaryAccount);
                    $rows = wf_TableRow($cells, 'row3');
                    $result .= $rows;
                }
            }
        }

        return($result);
    }

    /**
     * Renders visor users profile with associated cameras and some controls
     * 
     * @param int $userId
     * 
     * @return string
     */
    public function renderUserProfile($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $userData = $this->allUsers[$userId];
            if (!empty($userData)) {
                $userCamsCount = $this->getUserCamerasCount($userId);

                $cells = wf_TableCell(__('Name'), '30%', 'row2');
                $cells .= wf_TableCell($userData['realname']);
                $rows = wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Phone'), '', 'row2');
                $cells .= wf_TableCell($userData['phone']);
                $rows .= wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Charge'), '', 'row2');
                $chargeFlag = ($userData['chargecams']) ? wf_img_sized('skins/icon_active.gif', '', '12', '12') . ' ' . __('Yes') : wf_img_sized('skins/icon_inactive.gif', '', '12', '12') . ' ' . __('No');
                $cells .= wf_TableCell($chargeFlag);
                $rows .= wf_TableRow($cells, 'row3');

                //primary user account inline
                $rows .= $this->renderUserPrimaryAccount($userId);

                $result .= wf_TableBody($rows, '100%', 0, '');

                $result .= $this->renderUserControls($userId);

                if ($userCamsCount > 0) {
                    $result .= $this->renderCamerasContainer(self::URL_ME . self::URL_USERCAMS . $userId);
                } else {
                    $result .= $this->messages->getStyledMessage(__('User have no cameras assigned'), 'warning');
                }
            }
        } else {
            $result .= $this->messages->getStyledMessage(__('Something went wrong') . ': ' . __('User not exists') . ' [' . $userId . ']', 'error');
        }
        return ($result);
    }

    /**
     * Renders Visor user defaults controls set
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserControls($userId) {
        $result = '';
        if (cfr('VISOREDIT')) {
            if (isset($this->allUsers[$userId])) {
                $taskB = wf_tag('div', false, 'dashtask', 'style="height:75px; width:75px;"');
                $taskE = wf_tag('div', true);

                $result .= $taskB . wf_modalAuto(wf_img('skins/ukv/useredit.png', __('Edit user')), __('Edit user'), $this->renderUserEditInterface($userId)) . __('Edit') . $taskE;
                $result .= $taskB . wf_modalAuto(wf_img('skins/icon_king_big.png', __('Primary account')), __('Primary account'), $this->renderUserPrimaryEditForm($userId)) . __('Primary') . $taskE;
                $result .= $taskB . wf_modalAuto(wf_img('skins/annihilation.gif', __('Deleting user')), __('Deleting user'), $this->renderUserDeletionForm($userId), '') . __('Delete') . $taskE;

                $result .= wf_CleanDiv();
            }
        }
        return($result);
    }

    /**
     * Renders user primary account editing interface
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserPrimaryEditForm($userId) {
        $result = '';
        if (isset($this->allUsers[$userId])) {
            $currentUserData = $this->allUsers[$userId];
            $currentPrimaryAccount = $currentUserData['primarylogin'];
            $allUserCameras = $this->getUserCameras($userId);
            $camerasTmp = array();
            $selectedCamera = '';
            $camerasTmp[''] = '-';
            if (!empty($allUserCameras)) {

                foreach ($allUserCameras as $io => $each) {
                    if ($each['login'] == $currentPrimaryAccount) {
                        $selectedCamera = $each['login'];
                    }
                    $camerasTmp[$each['login']] = @$this->allUserData[$each['login']]['fulladress'] . ' - ' . @$this->allUserData[$each['login']]['ip'];
                }
            }

            $inputs = '';
            $inputs = wf_Selector('newprimarycameralogin', $camerasTmp, __('Camera'), $selectedCamera, true);
            $inputs .= __('Or') . wf_tag('br');
            $inputs .= wf_TextInput('newprimaryuserlogin', __('Login'), $currentPrimaryAccount, true, 20);
            $inputs .= wf_HiddenInput('editprimarycamerauserid', $userId);
            $inputs .= wf_delimiter();
            $inputs .= wf_Submit(__('Save'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        }
        return($result);
    }

    /**
     * Sets some account as primary for some user
     * 
     * @param int $userId
     * @param string $login
     * 
     * @return void
     */
    protected function setPrimaryAccount($userId, $login = '') {
        $userId = vf($userId, 3);
        $login = trim($login);

        if (isset($this->allUsers[$userId])) {
            $userCameras = $this->getUserCameras($userId);

            $currentPrimary = $this->allUsers[$userId]['primarylogin'];
            if ($currentPrimary != $login) {
                if ($this->isPrimaryAccountFree($login)) {
                    simple_update_field(self::TABLE_USERS, 'primarylogin', $login, "WHERE `id`='" . $userId . "'"); //setting primary account in profile
                    simple_update_field(self::TABLE_CAMS, 'primary', 0, "WHERE `visorid`='" . $userId . "'"); // dropping all camera primary flags
                    log_register('VISOR USER [' . $userId . '] CHANGE PRIMARY `' . $login . '`');
                    $cameraId = $this->getCameraIdByLogin($login);
                    if (!empty($cameraId)) {
                        simple_update_field(self::TABLE_CAMS, 'primary', '1', "WHERE `id`='" . $cameraId . "'"); //setting camera account as primary
                    }
                } else {
                    log_register('VISOR USER [' . $userId . '] FAIL PRIMARY BUSY');
                }
            }
        } else {
            log_register('VISOR USER [' . $userId . '] FAIL PRIMARY NOUSER');
        }
    }

    /**
     * Catches primary editing request and saves changes if required
     * 
     * @return void
     */
    public function savePrimary() {
        if (wf_CheckPost(array('editprimarycamerauserid'))) {
            $userId = vf($_POST['editprimarycamerauserid'], 3);
            $newPrimaryLogin = (wf_CheckPost(array('newprimarycameralogin'))) ? $_POST['newprimarycameralogin'] : '';
            if (wf_CheckPost(array('newprimaryuserlogin')) AND ! wf_CheckPost(array('newprimarycameralogin'))) {
                $newPrimaryLogin = $_POST['newprimaryuserlogin'];
            }
            $this->setPrimaryAccount($userId, $newPrimaryLogin);
        }
    }

    /**
     * user deletion form
     * 
     * @param int $userId existing user ID
     * 
     * @return string
     */
    protected function renderUserDeletionForm($userId) {
        $userId = vf($userId, 3);
        $inputs = __('Be careful, this module permanently deletes user and all data associated with it. Opportunities to raise from the dead no longer.') . ' <br>
               ' . __('To ensure that we have seen the seriousness of your intentions to enter the word сonfirm the field below.');
        $inputs .= wf_HiddenInput('userdeleteprocessing', $userId);
        $inputs .= wf_delimiter();
        $inputs .= wf_tag('input', false, '', 'type="text" name="deleteconfirmation" autocomplete="off"');
        $inputs .= wf_delimiter();
        $inputs .= wf_Submit(__('I really want to stop suffering User'));

        $result = wf_Form('', 'POST', $inputs, 'glamour');
        return ($result);
    }

    /**
     * Renders default cameras view container
     * 
     * @param string $url
     * 
     * @return string
     */
    public function renderCamerasContainer($url) {
        $result = '';
        $opts = '"order": [[ 0, "desc" ]]';
        $columns = array('ID', 'Primary', 'User', 'Address', 'IP', 'Tariff', 'Active', 'Balance', 'Credit', 'Actions');
        if ($this->altCfg['DN_ONLINE_DETECT']) {
            $columns = array('ID', 'Primary', 'User', 'Address', 'IP', 'Tariff', 'Active', 'Online', 'Balance', 'Credit', 'Actions');
        }
        $result .= wf_JqDtLoader($columns, $url, false, __('Cams'), 50, $opts);
        return($result);
    }

    /**
     * Renders ajax json backend for some user assigned cameras
     * 
     * @param int $userId
     * 
     * @return void
     */
    public function ajaxUserCams($userId) {
        $userId = vf($userId, 3);
        $json = new wf_JqDtHelper();
        $dnFlag = ($this->altCfg['DN_ONLINE_DETECT']) ? true : false;

        if (isset($this->allUsers[$userId])) {
            $allUserCams = $this->getUserCameras($userId);
            if (!empty($allUserCams)) {
                foreach ($allUserCams as $io => $each) {
                    $cameraUserData = @$this->allUserData[$each['login']];
                    $data[] = $each['id'];
                    $primaryFlag = ($each['primary']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                    $data[] = $primaryFlag;
                    $visorLinkLabel = $this->iconVisorUser() . ' ' . @$this->allUsers[$each['visorid']]['realname'];
                    $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['visorid'], $visorLinkLabel);
                    $data[] = $visorUserLink;
                    $cameraLinkLabel = web_profile_icon() . ' ' . $cameraUserData['fulladress'];
                    $cameraLink = wf_Link(self::URL_CAMPROFILE . $each['login'], $cameraLinkLabel);
                    $data[] = $cameraLink;
                    $data[] = @$cameraUserData['ip'];
                    $data[] = @$cameraUserData['Tariff'];
                    $cameraCash = @$cameraUserData['Cash'];
                    $cameraCredit = @$cameraUserData['Credit'];
                    $cameraState = '';
                    if ($cameraCash >= '-' . $cameraCredit) {
                        $cameraState = web_bool_led(true) . ' ' . __('Yes');
                    } else {
                        $cameraState = web_bool_led(false) . ' ' . __('No');
                    }
                    $data[] = $cameraState;
                    if ($dnFlag) {
                        $onlineState = web_bool_star(false) . ' ' . __('No');
                        if (file_exists(DATA_PATH . 'dn/' . $each['login'])) {
                            $onlineState = web_bool_star(true) . ' ' . __('Yes');
                        }
                        $data[] = $onlineState;
                    }
                    $data[] = $cameraCash;
                    $data[] = $cameraCredit;
                    $actLinks = wf_Link(self::URL_ME . self::URL_CAMVIEW . $each['id'], web_edit_icon());
                    $data[] = $actLinks;
                    $json->addRow($data);
                    unset($data);
                }
            }
        }
        $json->getJson();
    }

    /**
     * Returns default user icon coode
     * 
     * @param int size
     * 
     * @return string
     */
    public function iconVisorUser($size = '') {
        $size = vf($size, 3);
        $result = (!empty($size)) ? wf_img('skins/icon_camera_small.png') : wf_img_sized('skins/icon_camera_small.png', '', $size, $size);
        return($result);
    }

    /**
     * Renders ajax json backend for all available cameras
     * 
     * @return void
     */
    public function ajaxAllCams() {
        $json = new wf_JqDtHelper();
        $dnFlag = ($this->altCfg['DN_ONLINE_DETECT']) ? true : false;

        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                $cameraUserData = @$this->allUserData[$each['login']];
                $data[] = $each['id'];
                $primaryFlag = ($each['primary']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                $data[] = $primaryFlag;
                $visorLinkLabel = $this->iconVisorUser() . ' ' . @$this->allUsers[$each['visorid']]['realname'];
                $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['visorid'], $visorLinkLabel);
                $data[] = $visorUserLink;
                $cameraLinkLabel = web_profile_icon() . ' ' . $cameraUserData['fulladress'];
                $cameraLink = wf_Link(self::URL_CAMPROFILE . $each['login'], $cameraLinkLabel);
                $data[] = $cameraLink;
                $data[] = @$cameraUserData['ip'];
                $data[] = @$cameraUserData['Tariff'];
                $cameraCash = @$cameraUserData['Cash'];
                $cameraCredit = @$cameraUserData['Credit'];
                $cameraState = '';
                if ($cameraCash >= '-' . $cameraCredit) {
                    $cameraState = web_bool_led(true) . ' ' . __('Yes');
                } else {
                    $cameraState = web_bool_led(false) . ' ' . __('No');
                }
                $data[] = $cameraState;
                if ($dnFlag) {
                    $onlineState = web_bool_star(false) . ' ' . __('No');
                    if (file_exists(DATA_PATH . 'dn/' . $each['login'])) {
                        $onlineState = web_bool_star(true) . ' ' . __('Yes');
                    }
                    $data[] = $onlineState;
                }
                $data[] = $cameraCash;
                $data[] = $cameraCredit;
                $actLinks = wf_Link(self::URL_ME . self::URL_CAMVIEW . $each['id'], web_edit_icon());
                $data[] = $actLinks;
                $json->addRow($data);
                unset($data);
            }
        }

        $json->getJson();
    }

    /**
     * Renders initial camera creation interface
     * 
     * @param string $userLogin
     * 
     * @return string
     */
    public function renderCameraCreateInterface($userLogin) {
        $result = '';
        if (!empty($this->allUsers)) {
            if (cfr('VISOREDIT')) {
                $usersTmp = array();
                $usersTmp[''] = '-';
                foreach ($this->allUsers as $io => $each) {
                    $usersTmp[$each['id']] = $each['realname'];
                }

                $inputs = wf_Selector('newcameravisorid', $usersTmp, __('The user who will be assigned a new camera'), '', false);
                $inputs .= wf_delimiter();
                $inputs .= wf_HiddenInput('newcameralogin', $userLogin);
                $inputs .= wf_Submit(__('Create'));
                $result .= wf_Form('', 'POST', $inputs, 'glamour');
            } else {
                $failLabel = __('This user account is not associated with any existing Visor user or any camera account') . '. ';
                $failLabel .= __('Contact your system administrator to fix this issue') . '.';
                $result .= $this->messages->getStyledMessage($failLabel, 'warning');
            }
        } else {
            $result .= $this->messages->getStyledMessage(__('No existing Visor users avaliable, you must create one at least to assign cameras'), 'error');
        }
        return($result);
    }

    /**
     * Creates new camera account and assigns it to existing user
     * 
     * @return void
     */
    public function createCamera() {
        if (wf_CheckPost(array('newcameravisorid', 'newcameralogin'))) {
            $newVisorId = vf($_POST['newcameravisorid'], 3);
            $newCameraLogin = $_POST['newcameralogin'];
            $newCameraLoginF = mysql_real_escape_string($newCameraLogin);
            if (isset($this->allUsers[$newVisorId])) {
                if (!empty($newCameraLoginF)) {
                    $query = "INSERT INTO `" . self::TABLE_CAMS . "` (`id`,`visorid`,`login`,`primary`,`camlogin`,`campassword`,`port`,`dvrid`,`dvrlogin`,`dvrpassword`)"
                            . " VALUES "
                            . " (NULL,'" . $newVisorId . "','" . $newCameraLoginF . "','0','','','','','','');";
                    nr_query($query);
                    $newId = simple_get_lastid(self::TABLE_CAMS);
                    log_register('VISOR CAMERA CREATE [' . $newId . '] ASSIGN [' . $newVisorId . '] LOGIN (' . $newCameraLogin . ')');
                } else {
                    log_register('VISOR CAMERA CREATE FAIL EMPTY_LOGIN');
                }
            } else {
                log_register('VISOR CAMERA CREATE FAIL VISORID_NOT_EXISTS');
            }
        }
    }

    /**
     * Renders users editing interface
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserEditInterface($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $currentUserData = $this->allUsers[$userId];
            $sup = wf_tag('sup') . '*' . wf_tag('sup', true);
            $inputs = wf_HiddenInput('edituserid', $userId);
            $inputs .= wf_TextInput('editusername', __('Name') . $sup, $currentUserData['realname'], true, 25);
            $inputs .= wf_TextInput('edituserphone', __('Phone'), $currentUserData['phone'], true, 20, 'mobile');
            $inputs .= wf_CheckInput('edituserchargecams', __('Charge money from primary account for linked camera users if required'), true, $currentUserData['chargecams']);
            $inputs .= wf_delimiter();
            $inputs .= wf_Submit(__('Save'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        }
        return($result);
    }

    /**
     * Catches and saves user editing request if required
     * 
     * 
     * @return void
     */
    public function saveUser() {
        if (wf_CheckPost(array('edituserid', 'editusername'))) {
            $editUserId = vf($_POST['edituserid'], 3);
            if (isset($this->allUsers[$editUserId])) {
                $currentUserData = $this->allUsers[$editUserId];
                $where = " WHERE `id`='" . $editUserId . "'";
                $newUserName = $_POST['editusername'];
                $newUserPhone = $_POST['edituserphone'];
                $newCharge = (wf_CheckPost(array('edituserchargecams'))) ? 1 : 0;
                if ($currentUserData['realname'] != $newUserName) {
                    simple_update_field(self::TABLE_USERS, 'realname', $newUserName, $where);
                    log_register('VISOR USER [' . $editUserId . '] CHANGE NAME `' . $newUserName . '`');
                }

                if ($currentUserData['phone'] != $newUserPhone) {
                    simple_update_field(self::TABLE_USERS, 'phone', $newUserPhone, $where);
                    log_register('VISOR USER [' . $editUserId . '] CHANGE PHONE `' . $newUserPhone . '`');
                }

                if ($currentUserData['chargecams'] != $newCharge) {
                    simple_update_field(self::TABLE_USERS, 'chargecams', $newCharge, $where);
                    log_register('VISOR USER [' . $editUserId . '] CHANGE CHARGE `' . $newUserPhone . '`');
                }
            }
        }
    }

    /**
     * Returns existing camera deletion form
     * 
     * @param int $cameraId
     * 
     * @return string
     */
    protected function renderCameraDeletionForm($cameraId) {
        $cameraId = vf($cameraId, 3);
        $result = '';
        if (isset($this->allCams[$cameraId])) {
            $inputs = __('To ensure that we have seen the seriousness of your intentions to enter the word сonfirm the field below.');
            $inputs .= wf_delimiter();
            $inputs .= wf_tag('input', false, '', 'type="text" name="deleteconfirmation" autocomplete="off"');
            $inputs .= wf_tag('br');
            $inputs .= wf_HiddenInput('cameradeleteprocessing', $cameraId);
            $inputs .= wf_tag('br');
            $inputs .= wf_Submit(__('Delete camera'));


            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        }
        return($result);
    }

    /**
     * Deletes existing camera from database
     * 
     * @param int $cameraId
     * 
     * @return void/string on error
     */
    public function deleteCamera($cameraId) {
        $cameraId = vf($cameraId, 3);
        $result = '';
        if (isset($this->allCams[$cameraId])) {
            $cameraData = $this->allCams[$cameraId];
            $query = "DELETE  from `" . self::TABLE_CAMS . "` WHERE `id`='" . $cameraId . "';";
            nr_query($query);
            log_register('VISOR CAMERA DELETE [' . $cameraId . '] ASSIGNED [' . $cameraData['visorid'] . '] LOGIN (' . $cameraData['login'] . ')');
        } else {
            $result .= __('Something went wrong') . ': ' . __('No such camera exists') . ' [' . $cameraId . ']';
        }
        return($result);
    }

    /**
     * Renders camera profile with editing forms
     * 
     * @param int $cameraId
     * 
     * @return string 
     */
    public function renderCameraForm($cameraId) {
        $cameraId = vf($cameraId, 3);
        $result = '';
        if (isset($this->allCams[$cameraId])) {
            $cameraData = $this->allCams[$cameraId];
            $camProfile = $cameraData['login'];
            $usersTmp = array();
            $dvrTmp = array('' => '-');

            if (!empty($this->allUsers)) {
                foreach ($this->allUsers as $io => $each) {
                    $usersTmp[$each['id']] = $each['realname'];
                }
            }

            if (!empty($this->allDvrs)) {
                foreach ($this->allDvrs as $io => $each) {
                    $dvrTmp[$each['id']] = $each['ip'];
                }
            }

            //is camera internet user exists?
            if (isset($this->allUserData[$camProfile])) {
                $camProfileData = $this->allUserData[$camProfile];

                $cells = wf_TableCell(__('User'), '30%', 'row2');
                $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $cameraData['visorid'], $this->iconVisorUser('12') . ' ' . @$this->allUsers[$cameraData['visorid']]['realname']);
                $cells .= wf_TableCell($visorUserLink);
                $rows = wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Address'), '30%', 'row2');
                $camProfileLink = wf_Link(self::URL_CAMPROFILE . $camProfile, web_profile_icon() . ' ' . @$camProfileData['fulladress']);
                $cells .= wf_TableCell($camProfileLink);
                $rows .= wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('IP'), '30%', 'row2');
                $cells .= wf_TableCell($camProfileData['ip']);
                $rows .= wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Tariff'), '30%', 'row2');
                $cells .= wf_TableCell($camProfileData['Tariff']);
                $rows .= wf_TableRow($cells, 'row3');
                $cameraState = '';
                $cameraCash = $camProfileData['Cash'];
                $cameraCredit = $camProfileData['Credit'];
                if ($cameraCash >= '-' . $cameraCredit) {
                    $cameraState = wf_img_sized('skins/icon_active.gif', '', '12', '12') . ' ' . __('Yes');
                } else {
                    $cameraState = wf_img_sized('skins/icon_inactive.gif', '', '12', '12') . ' ' . __('No');
                }
                $cells = wf_TableCell(__('Active'), '30%', 'row2');
                $cells .= wf_TableCell($cameraState);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('Balance'), '30%', 'row2');
                $cells .= wf_TableCell($cameraCash);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('Credit'), '30%', 'row2');
                $cells .= wf_TableCell($cameraCredit);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('Camera login'), '30%', 'row2');
                $cells .= wf_TableCell($cameraData['camlogin']);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('Camera password'), '30%', 'row2');
                $cells .= wf_TableCell($cameraData['campassword']);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('Port'), '30%', 'row2');
                $cells .= wf_TableCell($cameraData['port']);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('DVR'), '30%', 'row2');
                $cells .= wf_TableCell(@$this->allDvrs[$cameraData['dvrid']]['ip']);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('DVR login'), '30%', 'row2');
                $cells .= wf_TableCell($cameraData['dvrlogin']);
                $rows .= wf_TableRow($cells, 'row3');

                $cells = wf_TableCell(__('DVR password'), '30%', 'row2');
                $cells .= wf_TableCell($cameraData['dvrpassword']);
                $rows .= wf_TableRow($cells, 'row3');

                $result .= wf_TableBody($rows, '100%', 0);
                $result .= wf_tag('br');

                $inputs = '';
                $inputs .= wf_HiddenInput('editcameraid', $cameraId);
                $inputs .= wf_Selector('editvisorid', $usersTmp, __('User'), $cameraData['visorid'], true);
                $inputs .= wf_TextInput('editcamlogin', __('Camera login'), $cameraData['camlogin'], true, 15);
                $inputs .= wf_TextInput('editcampassword', __('Camera password'), $cameraData['campassword'], true, 15);
                $inputs .= wf_TextInput('editport', __('Port'), $cameraData['port'], true, 5);
                $inputs .= wf_tag('br');
                $inputs .= wf_Selector('editdvrid', $dvrTmp, __('DVR'), $cameraData['dvrid'], true);
                $inputs .= wf_TextInput('editdvrlogin', __('DVR login'), $cameraData['dvrlogin'], true, 15);
                $inputs .= wf_TextInput('editdvrpassword', __('DVR password'), $cameraData['dvrpassword'], true, 15);
                $inputs .= wf_tag('br');
                $inputs .= wf_Submit(__('Save'));

                $cameraEditForm = wf_Form('', 'POST', $inputs, 'glamour');

                $result .= wf_Link(self::URL_ME . self::URL_USERVIEW . $cameraData['visorid'], $this->iconVisorUser() . ' ' . __('Back to user profile'), false, 'ubButton');
                if (cfr('VISOREDIT')) {
                    $result .= wf_modalAuto(web_edit_icon() . ' ' . __('Edit'), __('Edit'), $cameraEditForm, 'ubButton');
                    $result .= wf_modalAuto(web_delete_icon() . ' ' . __('Delete'), __('Delete'), $this->renderCameraDeletionForm($cameraId), 'ubButton');
                }
            } else {
                $result .= $this->messages->getStyledMessage(__('Something went wrong') . ': ' . __('User not exists') . ' (' . $cameraData['login'] . ')', 'error');
            }
        } else {
            $result .= $this->messages->getStyledMessage(__('Something went wrong') . ': ' . __('No such camera exists') . ' [' . $cameraId . ']', 'error');
        }
        return($result);
    }

    /**
     * Catches camera editing request and saves data if required
     * 
     * @return void
     */
    public function saveCamera() {
        if (wf_CheckPost(array('editcameraid'))) {
            $cameraId = vf($_POST['editcameraid'], 3);
            if (isset($this->allCams[$cameraId])) {
                $cameraData = $this->allCams[$cameraId];
                $where = " WHERE `id`='" . $cameraId . "'";

                $newVisorId = vf($_POST['editvisorid'], 3);
                $newCamLogin = $_POST['editcamlogin'];
                $newCamPassword = $_POST['editcampassword'];
                $newPort = vf($_POST['editport'], 3);
                $newDvrId = vf($_POST['editdvrid'], 3);
                $newDvrLogin = $_POST['editdvrlogin'];
                $newDvrPassword = $_POST['editdvrpassword'];

                if ($newVisorId != $cameraData['visorid']) {
                    simple_update_field(self::TABLE_CAMS, 'visorid', $newVisorId, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE ASSIGN [' . $newVisorId . ']');
                }

                if ($newCamLogin != $cameraData['camlogin']) {
                    simple_update_field(self::TABLE_CAMS, 'camlogin', $newCamLogin, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE LOGIN `' . $newCamLogin . '`');
                }

                if ($newCamPassword != $cameraData['campassword']) {
                    simple_update_field(self::TABLE_CAMS, 'campassword', $newCamPassword, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE PASSWORD `' . $newCamPassword . '`');
                }

                if ($newPort != $cameraData['port']) {
                    simple_update_field(self::TABLE_CAMS, 'port', $newPort, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE PORT `' . $newPort . '`');
                }

                if ($newDvrId != $cameraData['dvrid']) {
                    simple_update_field(self::TABLE_CAMS, 'dvrid', $newDvrId, $where);
                    if (!empty($newDvrId)) {
                        log_register('VISOR CAMERA [' . $cameraId . '] CHANGE DVR [' . $newDvrId . ']');
                    } else {
                        log_register('VISOR CAMERA [' . $cameraId . '] UNSET DVR');
                    }
                }

                if ($newDvrLogin != $cameraData['dvrlogin']) {
                    simple_update_field(self::TABLE_CAMS, 'dvrlogin', $newDvrLogin, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE DVRLOGIN `' . $newDvrLogin . '`');
                }

                if ($newDvrLogin != $cameraData['dvrpassword']) {
                    simple_update_field(self::TABLE_CAMS, 'dvrpassword', $newDvrPassword, $where);
                    log_register('VISOR CAMERA [' . $cameraId . '] CHANGE DVRPASSWORD `' . $newDvrPassword . '`');
                }
            }
        }
    }

    /**
     * Renders DVR creation form
     * 
     * @return string
     */
    protected function renderDVRsCreateForm() {
        $result = '';
        $sup = wf_tag('sup') . '*' . wf_tag('sup', true);

        $inputs = wf_HiddenInput('newdvr', 'true');
        $inputs .= wf_TextInput('newdvrip', __('IP') . $sup, '', true, 15, 'ip');
        $inputs .= wf_TextInput('newdvrport', __('Port'), '', true, 5, 'digits');
        $inputs .= wf_TextInput('newdvrlogin', __('Login'), '', true, 20);
        $inputs .= wf_TextInput('newdvrpassword', __('Password'), '', true, 20);
        $inputs .= wf_Submit(__('Create'));

        $result .= wf_Form('', 'POST', $inputs, 'glamour');
        return($result);
    }

    /**
     * Catches new DVR creation request/performs new DVR registering
     * 
     * @return void
     */
    public function createDVR() {
        if (wf_CheckPost(array('newdvr', 'newdvrip'))) {
            $ip = $_POST['newdvrip'];
            $ip_f = mysql_real_escape_string($ip);
            $port = vf($_POST['newdvrport'], 3);
            $login = mysql_real_escape_string($_POST['newdvrlogin']);
            $password = mysql_real_escape_string($_POST['newdvrpassword']);

            $query = "INSERT INTO `" . self::TABLE_DVRS . "` (`id`,`ip`,`port`,`login`,`password`) VALUES "
                    . "(NULL,'" . $ip_f . "','" . $port . "','" . $login . "','" . $password . "');";
            nr_query($query);
            $newId = simple_get_lastid(self::TABLE_DVRS);
            log_register('VISOR DVR CREATE [' . $newId . '] IP `' . $ip . '`');
        }
    }

    /**
     * Renders DVR editing form
     * 
     * @param int $dvrId
     * 
     * @return string
     */
    protected function renderDVREditForm($dvrId) {
        $dvrId = vf($dvrId, 3);
        $result = '';
        if (isset($this->allDvrs[$dvrId])) {
            $dvrData = $this->allDvrs[$dvrId];
            $sup = wf_tag('sup') . '*' . wf_tag('sup', true);

            $inputs = wf_HiddenInput('editdvrid', $dvrId);
            $inputs .= wf_TextInput('editdvrip', __('IP') . $sup, $dvrData['ip'], true, 15, 'ip');
            $inputs .= wf_TextInput('editdvrport', __('Port'), $dvrData['port'], true, 5, 'digits');
            $inputs .= wf_TextInput('editdvrlogin', __('Login'), $dvrData['login'], true, 12);
            $inputs .= wf_TextInput('editdvrpassword', __('Password'), $dvrData['password'], true, 12);
            $inputs .= wf_tag('br');
            $inputs .= wf_Submit(__('Save'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        } else {
            $result .= $this->messages->getStyledMessage(__('Something went wrong') . ': ' . __('No such DVR exists'), 'error');
        }
        return($result);
    }

    /**
     * Catches DVR modification request and saves new data to database if it was changed
     * 
     * @return void
     */
    public function saveDVR() {
        if (ubRouting::checkPost(array('editdvrid', 'editdvrip'))) {
            $dvrId = ubRouting::post('editdvrid', 'int');

            if (isset($this->allDvrs[$dvrId])) {
                $dvrData = $this->allDvrs[$dvrId];
                $where = " WHERE `id`='" . $dvrId . "'";
                $newIp = ubRouting::post('editdvrip', 'mres');
                $newPort = ubRouting::post('editdvrport', 'int');
                $newLogin = ubRouting::post('editdvrlogin', 'mres');
                $newPassword = ubRouting::post('editdvrpassword', 'mres');
                if ($dvrData['ip'] != $newIp) {
                    simple_update_field(self::TABLE_DVRS, 'ip', $newIp, $where);
                    log_register('VISOR DVR [' . $dvrId . '] CHANGE IP `' . $newIp . '`');
                }

                if ($dvrData['port'] != $newPort) {
                    simple_update_field(self::TABLE_DVRS, 'port', $newPort, $where);
                    log_register('VISOR DVR [' . $dvrId . '] CHANGE PORT `' . $newPort . '`');
                }

                if ($dvrData['login'] != $newLogin) {
                    simple_update_field(self::TABLE_DVRS, 'login', $newLogin, $where);
                    log_register('VISOR DVR [' . $dvrId . '] CHANGE LOGIN `' . $newLogin . '`');
                }

                if ($dvrData['password'] != $newPassword) {
                    simple_update_field(self::TABLE_DVRS, 'password', $newPassword, $where);
                    log_register('VISOR DVR [' . $dvrId . '] CHANGE PASSWORD `' . $newPassword . '`');
                }
            }
        }
    }

    /**
     * Renders existing DVRs list wit some controls
     * 
     * @return string
     */
    public function renderDVRsList() {
        $result = '';
        if (!empty($this->allDvrs)) {
            $cells = wf_TableCell(__('ID'));
            $cells .= wf_TableCell(__('IP'));
            $cells .= wf_TableCell(__('Port'));
            $cells .= wf_TableCell(__('Actions'));
            $rows = wf_TableRow($cells, 'row1');

            foreach ($this->allDvrs as $io => $each) {
                $cells = wf_TableCell($each['id']);
                $cells .= wf_TableCell($each['ip']);
                $cells .= wf_TableCell($each['port']);
                $actLinks = wf_JSAlert(self::URL_ME . self::URL_DELDVR . $each['id'], web_delete_icon(), $this->messages->getDeleteAlert()) . ' ';
                $actLinks .= wf_modalAuto(web_edit_icon(), __('Edit') . ' ' . $each['ip'], $this->renderDVREditForm($each['id']));
                $cells .= wf_TableCell($actLinks);
                $rows .= wf_TableRow($cells, 'row5');
            }

            $result .= wf_TableBody($rows, '100%', 0, 'sortable');
        } else {
            $result .= $this->messages->getStyledMessage(__('Nothing to show'), 'warning');
        }

        $result .= wf_delimiter();
        $result .= wf_modalAuto(wf_img('skins/ukv/add.png') . ' ' . __('Create'), __('Create'), $this->renderDVRsCreateForm(), 'ubButton');

        return($result);
    }

    /**
     * Checks is DVR used by some existing cameras
     * 
     * @param int $dvrId
     * 
     * @return bool
     */
    protected function isDVRProtected($dvrId) {
        $dvrId = vf($dvrId, 3);
        $result = false;
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['dvrid'] == $dvrId) {
                    $result = true;
                }
            }
        }
        return($result);
    }

    /**
     * Deletes existing DVR from database
     * 
     * @param int $dvrId
     * 
     * @return void/string on error
     */
    public function deleteDVR($dvrId) {
        $dvrId = vf($dvrId, 3);
        $result = '';
        if (isset($this->allDvrs[$dvrId])) {
            if (!$this->isDVRProtected($dvrId)) {
                $dvrData = $this->allDvrs[$dvrId];
                $query = "DELETE from `" . self::TABLE_DVRS . "` WHERE `id`='" . $dvrId . "';";
                nr_query($query);
                log_register('VISOR DVR DELETE [' . $dvrId . '] IP `' . $dvrData['ip'] . '`');
            } else {
                $result .= __('Something went wrong') . ': ' . __('This DVR is used for some cameras');
                log_register('VISOR DVR DELETE [' . $dvrId . '] TRY');
            }
        } else {
            $result .= __('Something went wrong') . ': ' . __('No such DVR exists') . ' [' . $dvrId . ']';
        }
        return($result);
    }

    /**
     * Performs default fee charge processing to prevent cameras offline
     * 
     * @return void
     */
    public function chargeProcessing() {
        $chargedCounter = 0;
        if (!empty($this->allUsers)) {
            //we need some fresh data
            $this->allUserData = zb_UserGetAllData();
            //and tariffs fee
            $allTariffsFee = zb_TariffGetPricesAll();
            foreach ($this->allUsers as $eachUserId => $eachUserData) {
                if (($eachUserData['chargecams']) AND ( !empty($eachUserData['primarylogin']))) {
                    if (isset($this->allUserData[$eachUserData['primarylogin']])) {
                        //further actions is required
                        $primaryAccountData = $this->allUserData[$eachUserData['primarylogin']];
                        $primaryAccountLogin = $primaryAccountData['login'];
                        $primaryAccountBalance = $primaryAccountData['Cash'];
                        $primaryAccountCredit = $primaryAccountData['Credit'];
                        $primaryAccountTariff = $primaryAccountData['Tariff'];
                        $primaryPossibleBalance = $primaryAccountBalance + $primaryAccountCredit; //global primary balance counter
                        $primaryAccountFee = $allTariffsFee[$primaryAccountTariff];
                        //loading user cameras
                        $userCameras = $this->getUserCameras($eachUserId);
                        if (!empty($userCameras)) {
                            foreach ($userCameras as $eachCameraId => $eachCameraData) {
                                if (isset($this->allUserData[$eachCameraData['login']])) {
                                    $cameraUserData = $this->allUserData[$eachCameraData['login']];
                                    $cameraLogin = $cameraUserData['login'];
                                    $cameraTariff = $cameraUserData['Tariff'];
                                    if (isset($allTariffsFee[$cameraTariff])) {
                                        $cameraBalance = $cameraUserData['Cash'];
                                        $cameraCredit = $cameraUserData['Credit'];
                                        $cameraFee = $allTariffsFee[$cameraTariff];
                                        $cameraLack = ($cameraBalance + $cameraCredit) - $cameraFee;
                                        //this camera needs some money to continue functioning
                                        if ($cameraLack < 0) {
                                            //is this not a same user?
                                            if ($cameraLogin != $primaryAccountLogin) {
                                                $chargeThisCam = false;
                                                //camera online priority
                                                if ($this->chargeMode == 1) {
                                                    $chargeThisCam = true;
                                                }

                                                //primary account internet priority
                                                if ($this->chargeMode == 2) {
                                                    $primaryPossibleBalance = ($primaryPossibleBalance) - abs($cameraLack);
                                                    if ($primaryPossibleBalance >= '-' . $primaryAccountCredit) {
                                                        //that doesnt disable primary account
                                                        $chargeThisCam = true;
                                                    } else {
                                                        //and this will
                                                        $chargeThisCam = false;
                                                    }
                                                }

                                                //perform money movement from primary account
                                                if ($chargeThisCam) {
                                                    //charge some money from primary account
                                                    zb_CashAdd($primaryAccountLogin, $cameraLack, 'add', 1, 'VISORCHARGE:' . $eachCameraId);
                                                    //and put in onto camera account
                                                    zb_CashAdd($cameraLogin, abs($cameraLack), 'correct', 1, 'VISORPUSH:' . $eachUserId);
                                                    //correcting operation here to prevent figure that as true payment in reports.
                                                    $chargedCounter++;
                                                }
                                            }
                                        }
                                    } else {
                                        log_register('VISOR CAMERA [' . $eachCameraId . '] CHARGE FAIL NO_TARIFF `' . $cameraTariff . '`');
                                    }
                                } else {
                                    log_register('VISOR CAMERA [' . $eachCameraId . '] CHARGE FAIL NO_USER (' . $eachCameraData['login'] . ')');
                                }
                            }
                        }
                    } else {
                        log_register('VISOR USER [' . $eachUserId . '] PRIMARY NO_USER (' . $eachUserData['primarylogin'] . ')');
                    }
                }
            }
            //flush old cached users data
            if ($chargedCounter > 0) {
                zb_UserGetAllDataCacheClean();
            }
        }
    }

}
