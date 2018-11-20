<?php

abstract class Notice
{
    public abstract function ok();
    public abstract function uploadFailed();
    public abstract function invalidAccount();
    public abstract function invalidPassword();
    public abstract function invalidParameter();
    public abstract function fileNotFound();
    public abstract function fileTooLarge();
    public abstract function fileBeyondSize();
    public abstract function invalidFileType();
    public abstract function registrationFailed();
    public abstract function fileDeleteFailed();
    public abstract function invalidCredential();
    public abstract function updateUserInfoFailed();
    public abstract function phoneNumberAlreadyExists();
    public abstract function thirdAccountAlreadyExists();
    public abstract function accountAlreadyExists();
    public abstract function thirdInvalidAccount();
    public abstract function accountNoExists();
    public abstract function unlinkLimit();
    public abstract function missingBankCard();
    public abstract function insufficientCredit();
    public abstract function identicalPassword();
    public abstract function tooManyCaptchaPort();
    public abstract function invalidPhone();
    public abstract function invalidQQ();
    public abstract function phoneAccountNoExists();
    public abstract function invalidOldPassword();

    public abstract function videoUnauthorized();
    public abstract function videoAuthorized();
    public abstract function multiLogon();
    public abstract function invalidCaptcha();
    public abstract function userBlocked();
    public abstract function tooManyAccounts();
    public abstract function addressBlocked();
    public abstract function tooManyGenderChange();
    public abstract function upgradeRequired();
    public abstract function alreadyReported();
    public abstract function paymentFailed();
    public abstract function captchaExpired();
    public abstract function deviceBlocked();

    public abstract function revokedUser();
    public abstract function isDomesticPhoneNumber();
    public abstract function invalidPhoneNumber();
    public abstract function saveFailed();
    public abstract function followFailed();

    public abstract function tooManyGroups();
    public abstract function tooManyGroupParticipants();
    public abstract function tooManyGroupProfileUpdates();
    public abstract function groupMemberAlreadyExists();
    public abstract function accepted();
    public abstract function tooManyGroupAdministrators();
    public abstract function tooManyGroupMemberships();
    public abstract function noContact();
    public abstract function invalidGroupActivityTime();
    public abstract function pendingGroupActivityAreadyExists();
    public abstract function createGroupActivityFailed();
    public abstract function noPendingGroupActivities();
    public abstract function groupActivityAlreadyEnrolled();
    public abstract function groupActivityEnrollFailed();

    public abstract function permissionDenied();
    public abstract function groupNotExist();
    public abstract function groupDismissed();
    public abstract function groupInvitationFailed();
    public abstract function runningGroupActivity();
    public abstract function photoNotExist();
    public abstract function groupActivityNotExist();
    public abstract function groupActivityRejected();
    public abstract function deletePhotoFailed();
    public abstract function messageExpired();
    public abstract function pendingWithdrawAlreadyExists();
    public abstract function samePassword();
    public abstract function blockTenMinutesForMaliciousActivity();
    public abstract function tooManyProfileUpdate();

    public abstract function invalidArgument();
    public abstract function unauthorized();
    public abstract function forbidden();
    public abstract function notFound();
    public abstract function requestTimeout();
    public abstract function tooManyRequest();
    public abstract function internalError();
    public abstract function poorConnection();
    public abstract function authenticationTimeout();
    public abstract function logonAgain();

    public abstract function eventUnLocked();
    public abstract function eventLocked();
    public abstract function eventNotExist();
    public abstract function invalidEvent();
    public abstract function quitEvent();
    private static $instance = NULL;

    public static function get()
    {
        if (Predicates::isNull(Notice::$instance)) {
            Notice::$instance = new I18N('Notice');
        }
        return Notice::$instance;
    }
}

?>
