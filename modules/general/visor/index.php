<?php

if (cfr('VISOR')) {

    $altCfg = $ubillingConfig->getAlter();

    if ($altCfg['VISOR_ENABLED']) {
        $visor = new UbillingVisor();
        //basic controls
        show_window('', $visor->panel());


        //users listing
        if (ubRouting::get('ajaxusers')) {
            $visor->ajaxUsersList();
        }

        //user cameras listing
        if (ubRouting::checkGet(array('ajaxusercams'))) {
            $visor->ajaxUserCams(ubRouting::get('ajaxusercams', 'int'));
        }

        //all available cameras listing
        if (ubRouting::get('ajaxallcams')) {
            $visor->ajaxAllCams();
        }

        //users creation
        if (ubRouting::checkPost(array('newusercreate', 'newusername'))) {
            $visor->createUser();
            ubRouting::nav($visor::URL_ME . $visor::URL_USERS);
        }

        //all cameras listing
        if (ubRouting::get('cams')) {
            show_window(__('Cams'), $visor->renderCamerasContainer($visor::URL_ME . $visor::URL_ALLCAMS));
        }

        //users deletion
        if (ubRouting::checkPost(array('userdeleteprocessing', 'deleteconfirmation'))) {
            if (ubRouting::post('deleteconfirmation') == 'confirm') {
                $deletionResult = $visor->deleteUser(ubRouting::post('userdeleteprocessing', 'int'));
                if (empty($deletionResult)) {
                    ubRouting::nav($visor::URL_ME . $visor::URL_USERS);
                } else {
                    show_error($deletionResult);
                    show_window('', wf_BackLink($visor::URL_ME . $visor::URL_USERS));
                }
            } else {
                log_register('VISOR USER DELETE TRY [' . ubRouting::post('userdeleteprocessing') . ']');
            }
        }

        //camera creation
        if (ubRouting::checkPost(array('newcameravisorid', 'newcameralogin'))) {
            $visor->createCamera();
            ubRouting::nav($visor::URL_ME . $visor::URL_USERVIEW . ubRouting::post('newcameravisorid', 'int'));
        }

        //user editing
        if (ubRouting::checkPost(array('edituserid', 'editusername'))) {
            $visor->saveUser();
            ubRouting::nav($visor::URL_ME . $visor::URL_USERVIEW . ubRouting::post('edituserid'));
        }

        //primary camera editing
        if (ubRouting::checkPost(array('editprimarycamerauserid'))) {
            $visor->savePrimary();
            ubRouting::nav($visor::URL_ME . $visor::URL_USERVIEW . ubRouting::post('editprimarycamerauserid'));
        }


        //users list rendering
        if (ubRouting::checkGet(array('users'))) {
            show_window(__('Users'), $visor->renderUsers());
            zb_BillingStats(true);
        }

        //camera options editing
        if (ubRouting::checkPost(array('editcameraid'))) {
            $visor->saveCamera();
            ubRouting::nav($visor::URL_ME . $visor::URL_CAMVIEW . ubRouting::post('editcameraid'));
        }


        //camera user detection on black magic action
        if (ubRouting::checkGet(array('username'))) {
            $userLogin = ubRouting::get('username');
            $userIdDetected = $visor->getCameraUser($userLogin);
            if (!empty($userIdDetected)) {
                ubRouting::nav($visor::URL_ME . $visor::URL_USERVIEW . $userIdDetected);
            } else {
                $primaryVisorId = $visor->getPrimaryAccountUserId($userLogin);
                if ($primaryVisorId) {
                    ubRouting::nav($visor::URL_ME . $visor::URL_USERVIEW . $primaryVisorId);
                } else {
                    //new camera creation interface
                    show_window(__('Create camera'), $visor->renderCameraCreateInterface($userLogin));
                    show_window('', web_UserControls($userLogin));
                }
            }
        }


        //user profile rendering
        if (ubRouting::checkGet(array('showuser'))) {
            show_window(__('User profile'), $visor->renderUserProfile(ubRouting::get('showuser')));
        }

        //camera profile/editing interface
        if (ubRouting::checkGet(array('showcamera'))) {
            show_window(__('Camera'), $visor->renderCameraForm(ubRouting::get('showcamera')));
        }

        //new DVR creation
        if (ubRouting::checkPost(array('newdvr'))) {
            $visor->createDVR();
            ubRouting::nav($visor::URL_ME . $visor::URL_DVRS);
        }

        //deleting existing DVR
        if (ubRouting::checkGet(array('deletedvrid'))) {
            $dvrDeletionResult = $visor->deleteDVR(ubRouting::get('deletedvrid'));
            if (empty($dvrDeletionResult)) {
                ubRouting::nav($visor::URL_ME . $visor::URL_DVRS);
            } else {
                show_error($dvrDeletionResult);
                show_window('', wf_BackLink($visor::URL_ME . $visor::URL_DVRS));
            }
        }

        //deleting existing camera
        if (ubRouting::checkPost(array('cameradeleteprocessing', 'deleteconfirmation'))) {
            if (ubRouting::post('deleteconfirmation') == 'confirm') {
                $camDeletionResult = $visor->deleteCamera(ubRouting::post('cameradeleteprocessing', 'int'));
                if (empty($camDeletionResult)) {
                    ubRouting::nav($visor::URL_ME . $visor::URL_CAMS);
                } else {
                    show_error($camDeletionResult);
                }
            }
        }
        
        //DVR editing
        if (ubRouting::checkPost(array('editdvrid', 'editdvrip'))) {
            $visor->saveDVR();
            ubRouting::nav($visor::URL_ME.$visor::URL_DVRS);
        }

        //existing DVR listing
        if (ubRouting::checkGet(array('dvrs'))) {
            show_window(__('DVRs'), $visor->renderDVRsList());
        }
    } else {
        show_error(__('This module is disabled'));
    }
} else {
    show_error(__('Access denied'));
}
?>