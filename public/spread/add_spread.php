<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       public/spread/add_spread.php
 *  \ingroup    saturne
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOLOGIN')) { // This means this output page does not require to be logged
    define('NOLOGIN', 1);
}
if (!defined('NOCSRFCHECK')) { // We accept to go on this page from external website
    define('NOCSRFCHECK', 1);
}
if (!defined('NOIPCHECK')) { // Do not check IP defined into conf $dolibarr_main_restrict_ip
    define('NOIPCHECK', 1);
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', 1);
}

// Load Saturne environment
if (file_exists('../../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../../saturne/saturne.main.inc.php';
} elseif (file_exists('../../../saturne.main.inc.php')) {
    require_once __DIR__ . '/../../../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

// Get module parameters
// The Saturne media block posts its own module_name with the photo upload; it must not be taken
// as this page's module context (the upload handler reads it into its own variable instead).
$moduleName   = (GETPOST('action', 'aZ09') == 'uploadPhoto') ? '' : GETPOST('module_name', 'alpha');
$objectType   = GETPOST('object_type', 'alpha');
$documentType = GETPOST('document_type', 'alpha');

$moduleNameLowerCase = strtolower($moduleName);

// Libraries
if (isModEnabled('societe')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
}

require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/saturne/class/saturnesignature.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/saturne/class/saturnemail.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/doliletter/class/doliletterattendancesheet.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/doliletter/class/doliletterspreadsignature.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/doliletter/lib/doliletter_spread.lib.php';
// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user, $modulepart;

if (!isset($_SESSION['dol_login'])) {
    $user->loadDefaultValues();
} else {
    $user->fetch('', $_SESSION['dol_login'], '', 1);
    $user->loadRights();
}

$permissiontoadd           = $user->hasRight('doliletter', 'spread', 'write');
$permissiontoshowsignature = $user->hasRight('doliletter', 'spreadsignature', 'read');
$isLogged                  = !empty($_SESSION['dol_login']);
$publicRegisterEnabled     = getDolGlobalInt('DOLILETTER_SPREAD_PUBLIC_REGISTER') > 0;

// Load translation files required by the page
// companies holds the Firstname / Lastname / Phone labels of the public registration form
saturne_load_langs(['doliletter@doliletter', 'companies', 'errors']);

// Get parameters
$id                 = GETPOST('id', 'int');
$ref                = GETPOST('ref', 'alpha');
$action             = GETPOST('action', 'aZ09');
$contextpage        = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : $objectType . 'signature'; // To manage different context of search
$cancel             = GETPOST('cancel', 'aZ09');
$backtopage         = GETPOST('backtopage', 'alpha');
$attendantTableMode = (GETPOSTISSET('attendant_table_mode') ? GETPOST('attendant_table_mode', 'alpha') : 'advanced');
$subaction          = GETPOST('subaction', 'alpha');

$sign               = GETPOST('sign', 'alpha');

// Initialize technical objects
$className       = ucfirst($objectType);
// Spread signature: same object as the Saturne one, plus the `json` column holding the signatory answers
$signatory       = new DoliletterSpreadSignature($db);
$saturneMail     = new SaturneMail($db, $moduleNameLowerCase, $objectType);
$usertmp         = new User($db);
$attendanceSheet = new DoliletterAttendanceSheet($db, $moduleNameLowerCase);
$form            = new Form($db);
$ecmFiles        = new EcmFiles($db);
if (isModEnabled('societe')) {
    $thirdparty = new Societe($db);
    $contact    = new Contact($db);
}

$objectsMetadata    = saturne_get_objects_metadata();

$attendanceSheet->fetch(0, '', ' AND object_type = ' . "'" . $objectType  . "'" . ' AND fk_object = ' . $id);

$objectsMetadata[$objectType]['object']->fetch($id);
$objectRef   = $objectsMetadata[$objectType]['object']->ref;
$objectLabel = $objectsMetadata[$objectType]['object']->{$objectsMetadata[$objectType]['label_field']} ?? '';

// Prevention plan specifics: risks, protections, required certifications + uploaded certification photos
// Loaded before the actions: signing is refused while a mandatory certification has no answer.
$isPreventionPlan     = ($objectType === 'digiriskdolibarr_preventionplan');
$ppRisks              = [];
$ppProtections        = [];
$ppCertifications     = [];
$ppOrphanProtections  = [];
$ppRecapProtections   = [];
$certificationOptions = [];
$ppCertBaseDir        = '';
if ($isPreventionPlan) {
    saturne_load_langs(['digiriskdolibarr@digiriskdolibarr']);
    dol_include_once('/digiriskdolibarr/class/preventionplan.class.php');
    dol_include_once('/digiriskdolibarr/class/riskanalysis/risk.class.php');
    dol_include_once('/digiriskdolibarr/lib/digiriskdolibarr_mobile.lib.php');

    $ppObject             = $objectsMetadata[$objectType]['object'];
    $certificationOptions = digiriskGetCertificationOptions();

    // Protections + certifications from the extrafields
    $ppObject->fetch_optionals();
    $ppProtections    = !empty($ppObject->array_options['options_mobile_protections'])   ? json_decode($ppObject->array_options['options_mobile_protections'], true)   : [];
    $ppCertifications = !empty($ppObject->array_options['options_mobile_certifications']) ? json_decode($ppObject->array_options['options_mobile_certifications'], true) : [];
    if (!is_array($ppProtections)) {
        $ppProtections = [];
    }

    // Map protection position -> signalisation picto/name
    $signalisationFile = DOL_DOCUMENT_ROOT . '/custom/digiriskdolibarr/js/json/signalisationCategories.json';
    $ppProtectionMap   = [];
    if (file_exists($signalisationFile)) {
        foreach ((json_decode(file_get_contents($signalisationFile), true) ?: []) as $signalisationCategory) {
            $ppProtectionMap[$signalisationCategory['position']] = $signalisationCategory;
        }
    }

    // Risks (prevention plan lines), each carrying the protections that apply to it and the
    // photos taken on site from the mobile interface
    $ppLine  = new PreventionPlanLine($db);
    $ppRisk  = new Risk($db);
    $ppLines = $ppLine->fetchAll('', '', 0, 0, ['fk_preventionplan' => $ppObject->id]);
    if (is_array($ppLines)) {
        foreach ($ppLines as $ppLineItem) {
            $thumb        = $ppRisk->getDangerCategory($ppLineItem);
            $riskCategory = (int) $ppLineItem->category;

            $riskProtections = [];
            foreach ($ppProtections as $ppProtectionItem) {
                if (!isset($ppProtectionItem['risk_category']) || (int) $ppProtectionItem['risk_category'] !== $riskCategory || !isset($ppProtectionMap[$ppProtectionItem['position']])) {
                    continue;
                }
                $riskProtections[] = [
                    'thumb'   => DOL_URL_ROOT . '/custom/digiriskdolibarr/img/' . $ppProtectionMap[$ppProtectionItem['position']]['name_thumbnail'],
                    'name'    => $ppProtectionMap[$ppProtectionItem['position']]['name'],
                    'comment' => $ppProtectionItem['comment'] ?? '',
                ];
            }

            // Public page: the photos go through the Saturne image wrapper, document.php would
            // ask the anonymous visitor to log in
            $riskPhotos    = [];
            $riskPhotoDir  = digiriskMobileRiskPhotoDir('preventionplan', $ppObject->ref, $riskCategory);
            $riskPhotoPath = 'preventionplan/' . dol_sanitizeFileName($ppObject->ref) . '/risks/' . $riskCategory . '/';
            if (dol_is_dir($riskPhotoDir)) {
                foreach (dol_dir_list($riskPhotoDir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'name') as $riskPhotoFile) {
                    $riskPhotos[] = DOL_URL_ROOT . '/custom/saturne/utils/viewimage.php?modulepart=digiriskdolibarr&entity=' . $conf->entity . '&file=' . urlencode($riskPhotoPath . $riskPhotoFile['name']);
                }
            }

            $ppRisks[] = [
                'category'    => $riskCategory,
                'thumb'       => ($thumb != -1) ? DOL_URL_ROOT . '/custom/digiriskdolibarr/img/categorieDangers/' . $thumb . '.png' : '',
                'name'        => $ppRisk->getDangerCategoryName($ppLineItem),
                'comment'     => $ppLineItem->description,
                'protections' => $riskProtections,
                'photos'      => $riskPhotos,
            ];
        }
    }

    // Protections of plans created before they were attached to a risk: nothing links them to a
    // block, they would silently disappear from the public page
    $ppOrphanProtections = [];
    foreach ($ppProtections as $ppProtectionItem) {
        if (empty($ppProtectionItem['risk_category']) && isset($ppProtectionMap[$ppProtectionItem['position']])) {
            $ppOrphanProtections[] = [
                'thumb'   => DOL_URL_ROOT . '/custom/digiriskdolibarr/img/' . $ppProtectionMap[$ppProtectionItem['position']]['name_thumbnail'],
                'name'    => $ppProtectionMap[$ppProtectionItem['position']]['name'],
                'comment' => $ppProtectionItem['comment'] ?? '',
            ];
        }
    }

    // Recapitulatif de fin de page : les moyens de prevention du plan, une seule fois chacun, un
    // meme moyen revenant sur plusieurs risques
    foreach ($ppRisks as $ppRiskItem) {
        foreach ($ppRiskItem['protections'] as $ppRiskProtection) {
            $ppRecapProtections[$ppRiskProtection['name']] = $ppRiskProtection;
        }
    }
    foreach ($ppOrphanProtections as $ppOrphanProtection) {
        $ppRecapProtections[$ppOrphanProtection['name']] = $ppOrphanProtection;
    }

    // Base directory of uploaded certification photos, same resolution as saturne_render_media_block()
    $ppUploadBase  = !empty($conf->digiriskdolibarr->dir_output) ? $conf->digiriskdolibarr->dir_output : $conf->ecm->dir_output . '/digiriskdolibarr';
    $ppCertBaseDir = $ppUploadBase . '/preventionplan/' . dol_sanitizeFileName($ppObject->ref) . '/certifications';
}

// Signatory the ?sign= token points to, resolved before the actions so a visitor can only answer for themselves
$signTokenSignatoryId = 0;
if (!empty($sign)) {
    $tokenSignatory = new SaturneSignature($db);
    $tokenSignatory->fetch(0, '', ' AND t.signature_url = "' . $db->escape($sign) . '"');
    $signTokenSignatoryId = ($tokenSignatory->id > 0) ? $tokenSignatory->id : 0;
}

if ($action == 'add_spread_user') {
    $result = doliletter_spread_ensure_attendance_sheet($attendanceSheet, $objectsMetadata, $objectType, $id, $user);
    if ($result < 0) {
        setEventMessages($attendanceSheet->error, $attendanceSheet->errors, 'errors');
        exit;
    }

    $tmpSignatory = new SaturneSignature($db, $moduleNameLowerCase, $attendanceSheet->element);
    $tmpSignatory->element_id     = 0;
    $tmpSignatory->element_type   = 'user';
    $tmpSignatory->role           = '';
    $tmpSignatory->object_type    = $attendanceSheet->element;
    $tmpSignatory->fk_object      = $attendanceSheet->id;
    $tmpSignatory->module_name    = $moduleNameLowerCase;
    $tmpSignatory->status         = $tmpSignatory::STATUS_PENDING_SIGNATURE;
    $tmpSignatory->signature_url  = generate_random_id();

    $result = $tmpSignatory->create($user);
    if ($result < 0) {
        setEventMessages($signatory->error, $signatory->errors, 'errors');
        exit;
    }
    $action = '';
}

// Free registration from the public page: anybody holding the link declares themselves with their
// identity only, no Dolibarr user and no login involved.
if ($action == 'register_public_signatory') {
    if (!$publicRegisterEnabled) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorNotAllowed') . '">';
        exit;
    }

    $data      = json_decode(file_get_contents('php://input'), true);
    $firstname = dol_string_nohtmltag(trim($data['firstname'] ?? ''));
    $lastname  = dol_string_nohtmltag(trim($data['lastname'] ?? ''));
    $email     = dol_string_nohtmltag(trim($data['email'] ?? ''));
    $phone     = dol_string_nohtmltag(trim($data['phone'] ?? ''));

    $requiredFields = ['Firstname' => $firstname, 'Lastname' => $lastname, 'Email' => $email, 'Phone' => $phone];
    foreach ($requiredFields as $requiredLabel => $requiredValue) {
        if (dol_strlen($requiredValue) == 0) {
            echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorFieldRequired', $langs->transnoentities($requiredLabel)) . '">';
            exit;
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorBadEMail', dol_escape_htmltag($email)) . '">';
        exit;
    }

    $result = doliletter_spread_ensure_attendance_sheet($attendanceSheet, $objectsMetadata, $objectType, $id, $user);
    if ($result < 0) {
        // A bare "Error" leaves both the visitor and the support with nothing to go on
        echo '<input type="hidden" id="error" value="' . dol_escape_htmltag($langs->transnoentities('ErrorSpreadRegisterFailed', doliletter_spread_get_object_error($attendanceSheet, $langs))) . '">';
        exit;
    }

    $tmpSignatory = new SaturneSignature($db, $moduleNameLowerCase, $attendanceSheet->element);

    // Someone coming back with the same email lands on their own page again instead of piling up duplicates
    $alreadyRegistered = $tmpSignatory->fetchAll('', '', 1, 0, ['customsql' => 'fk_object = ' . ((int) $attendanceSheet->id) . ' AND object_type = "' . $db->escape($attendanceSheet->element) . '" AND email = "' . $db->escape($email) . '"']);
    if (is_array($alreadyRegistered) && !empty($alreadyRegistered)) {
        $tmpSignatory = current($alreadyRegistered);
    } else {
        $tmpSignatory->element_id    = 0;
        $tmpSignatory->element_type  = DOLILETTER_SPREAD_EXTERNAL_ELEMENT_TYPE;
        $tmpSignatory->role          = '';
        $tmpSignatory->firstname     = $firstname;
        $tmpSignatory->lastname      = $lastname;
        $tmpSignatory->email         = $email;
        $tmpSignatory->phone         = $phone;
        $tmpSignatory->object_type   = $attendanceSheet->element;
        $tmpSignatory->fk_object     = $attendanceSheet->id;
        $tmpSignatory->module_name   = $moduleNameLowerCase;
        $tmpSignatory->status        = $tmpSignatory::STATUS_PENDING_SIGNATURE;
        $tmpSignatory->signature_url = generate_random_id();

        $result = $tmpSignatory->create($user);
        if ($result < 0) {
            echo '<input type="hidden" id="error" value="' . dol_escape_htmltag($langs->transnoentities('ErrorSpreadRegisterFailed', doliletter_spread_get_object_error($tmpSignatory, $langs))) . '">';
            exit;
        }

        $attendanceSheet->context = ['user' => $firstname . ' ' . $lastname, 'old_user' => ''];
        $attendanceSheet->call_trigger('SPREAD_ADD_USER', $user);
    }

    $registerUrl = dol_buildpath('/doliletter/public/spread/add_spread.php', 1) . '?id=' . $id . '&object_type=' . $objectType . '&sign=' . urlencode($tmpSignatory->signature_url);

    echo '<input type="hidden" id="redirect" value="' . dol_escape_htmltag($registerUrl) . '">';
    exit;
}

if ($action == 'remove_spread_user') {
    $signatory_id = GETPOSTINT('signatory_id');

    $signatory->fetch($signatory_id);
    if ($signatory->id > 0) {
        $result = $signatory->delete($user);
    }
    $action = '';
}

if ($action == 'update_spread_user') {
    $signatory_id = GETPOSTINT('signatory_id');
    $signatory->fetch($signatory_id);
    if ($signatory->id > 0) {
        $tmpUser = new User($db);
        $tmpUser->fetch(GETPOSTINT('user_id'));
        $attendanceSheet->context = [
            'user' => $tmpUser->firstname . ' ' . $tmpUser->lastname,
            'old_user' => $signatory->element_id ? $signatory->firstname . ' ' . $signatory->lastname : ''
        ];
        $attendanceSheet->call_trigger('SPREAD_ADD_USER', $user);

        $signatory->element_id   = GETPOSTINT('user_id');
        $signatory->element_type = 'user';

        $signatory->firstname = $tmpUser->firstname;
        $signatory->lastname  = $tmpUser->lastname;

        $signatory->update($user);
    }
    $action = '';
}

// Declare a mandatory document as not applicable, so it no longer has to be uploaded
if ($action == 'set_cert_not_concerned') {
    $data         = json_decode(file_get_contents('php://input'), true);
    $signatoryId  = (int) ($data['signatory_id'] ?? 0);
    $certCode     = dol_string_nohtmltag(trim($data['cert_code'] ?? ''));
    $notConcerned = !empty($data['not_concerned']);

    $tmpSignatory = new DoliletterSpreadSignature($db);
    $tmpSignatory->fetch($signatoryId);

    $belongsToSpread = $tmpSignatory->id > 0 && $tmpSignatory->fk_object == $attendanceSheet->id && $tmpSignatory->object_type == $attendanceSheet->element;
    // Without a session the ?sign= token is the only proof of identity: answer for yourself only
    $isOwnAnswer = $isLogged || ($signTokenSignatoryId > 0 && $signTokenSignatoryId == $tmpSignatory->id);

    if (!$belongsToSpread || !$isOwnAnswer || dol_strlen($certCode) == 0) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorNotAllowed') . '">';
        exit;
    }

    $result = doliletter_spread_set_not_concerned_certification($tmpSignatory, $certCode, $notConcerned, $user);
    if ($result < 0) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('Error') . '">';
        exit;
    }

    echo '<input type="hidden" id="success" value="' . $langs->transnoentities('RecordSaved') . '">';
    exit;
}

// Record that the visitor has taken note of one risk, its protections and its photos
if ($action == 'acknowledge_risk') {
    $data         = json_decode(file_get_contents('php://input'), true);
    $signatoryId  = (int) ($data['signatory_id'] ?? 0);
    $riskCategory = (int) ($data['risk_category'] ?? 0);

    $tmpSignatory = new DoliletterSpreadSignature($db);
    $tmpSignatory->fetch($signatoryId);

    $belongsToSpread = $tmpSignatory->id > 0 && $tmpSignatory->fk_object == $attendanceSheet->id && $tmpSignatory->object_type == $attendanceSheet->element;
    // Without a session the ?sign= token is the only proof of identity: answer for yourself only
    $isOwnAnswer = $isLogged || ($signTokenSignatoryId > 0 && $signTokenSignatoryId == $tmpSignatory->id);
    $isKnownRisk = in_array($riskCategory, array_map('intval', array_column($ppRisks, 'category')), true);

    if (!$belongsToSpread || !$isOwnAnswer || !$isKnownRisk) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorNotAllowed') . '">';
        exit;
    }

    if (doliletter_spread_acknowledge_risk($tmpSignatory, $riskCategory, $user) < 0) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('Error') . '">';
        exit;
    }

    echo '<input type="hidden" id="success" value="' . $langs->transnoentities('RecordSaved') . '">';
    exit;
}

if ($action == 'validate_signature') {
    $signatory_id = GETPOSTINT('signatory_id');
    $signatory->fetch($signatory_id);
    if ($signatory->id > 0) {
        $data = json_decode(file_get_contents('php://input'), true);

        // Risks read on the page and sent with the signature: a visitor signing from the attendee
        // table has no ?sign= link, nothing could be recorded while they were ticking the blocks
        if ($isPreventionPlan && !empty($ppRisks) && !empty($data['acknowledged_risks']) && is_array($data['acknowledged_risks'])) {
            $knownRiskCategories = array_map('intval', array_column($ppRisks, 'category'));
            foreach ($data['acknowledged_risks'] as $acknowledgedRisk) {
                if (in_array((int) $acknowledgedRisk, $knownRiskCategories, true)) {
                    doliletter_spread_acknowledge_risk($signatory, (int) $acknowledgedRisk, $user);
                }
            }
        }

        // Every risk must have been acknowledged before signing
        if ($isPreventionPlan && !empty($ppRisks)) {
            $pendingRisks = doliletter_spread_get_pending_risks($ppRisks, doliletter_spread_get_acknowledged_risks($signatory));
            if (!empty($pendingRisks)) {
                echo '<input type="hidden" id="error" value="' . dol_escape_htmltag($langs->transnoentities('ErrorRisksNotAcknowledged', implode(', ', array_column($pendingRisks, 'name')))) . '">';
                exit;
            }
        }

        // Mandatory documents must be either uploaded or declared not applicable before signing
        if ($isPreventionPlan && !empty($ppCertifications)) {
            $certificationStates   = doliletter_spread_get_certification_states($ppCertifications, $certificationOptions, $ppCertBaseDir, $signatory->id, doliletter_spread_get_not_concerned_certifications($signatory));
            $pendingCertifications = doliletter_spread_get_pending_certifications($certificationStates);
            if (!empty($pendingCertifications)) {
                echo '<input type="hidden" id="error" value="' . dol_escape_htmltag($langs->transnoentities('ErrorMandatoryCertificationsMissing', implode(', ', array_column($pendingCertifications, 'label')))) . '">';
                exit;
            }
        }

        $signature = $data['signature'] ?? '';

        if (!empty($signature)) {
            $signatory->signature      = $signature;
            $signatory->status         = $signatory::STATUS_SIGNED;
            $signatory->signature_date = dol_now();
            // Keep signature_url unchanged: the ?sign= link must stay valid so the signatory
            // still lands on their own single-person page (signed state) after signing.
            $signatory->update($user);
        }
    }
    $action = '';
}

if ($action == 'save_public_note') {
    if ($attendanceSheet->id > 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        $note = $data['note_public'] ?? '';

        $attendanceSheet->note_public = $note;
        $attendanceSheet->update($user);
    }
    $action = '';
}


// Photo upload posted by the Saturne media block — same contract as saturne/admin/media.php.
// Note: the media JS posts its own "module_name", which would otherwise clobber this page's
// $moduleName/$moduleNameLowerCase, so it is read into a dedicated variable here.
if ($action == 'uploadPhoto' && !empty($conf->global->MAIN_UPLOAD_DOC)) {
    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    $mediaModuleName = dol_strtolower(GETPOST('module_name', 'alpha'));
    $mediaSubDir     = GETPOST('sub_dir', 'alpha');

    if (!empty($mediaModuleName) && strpos($mediaSubDir, '..') === false) {
        $uploadDir = !empty($conf->$mediaModuleName->dir_output)
            ? $conf->$mediaModuleName->dir_output
            : $conf->ecm->dir_output . '/' . $mediaModuleName;
        if (!empty($mediaSubDir)) {
            $uploadDir .= '/' . $mediaSubDir;
        }

        if (!dol_is_dir($uploadDir)) {
            dol_mkdir($uploadDir);
        }

        // Validate that every uploaded file is a real image via MIME type
        $uploadedFiles = isset($_FILES['userfile']) ? $_FILES['userfile'] : [];
        $invalidFile   = false;
        if (!empty($uploadedFiles['tmp_name'])) {
            $tmpNames = is_array($uploadedFiles['tmp_name']) ? $uploadedFiles['tmp_name'] : [$uploadedFiles['tmp_name']];
            foreach ($tmpNames as $tmpName) {
                if (empty($tmpName)) {
                    continue;
                }
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpName);
                if (strpos($mimeType, 'image/') !== 0) {
                    $invalidFile = true;
                    break;
                }
            }
        }

        if (!$invalidFile) {
            $allowOverwrite = GETPOSTINT('overwrite') ? 1 : 0;
            dol_add_file_process($uploadDir, $allowOverwrite, 1, 'userfile', '', null, '', 1);
        }
    }
    $action = '';
}

if ($action == 'send_quick_sign_email') {
    if (empty($conf->global->DOLILETTER_SPREAD_QUICK_SIGN)) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorNotAllowed') . '">';
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorFieldRequired', $langs->transnoentities('Email')) . '">';
        exit;
    }

    $tmpUser = new User($db);
    $tmpUser->fetch(0, '', '', 0, -1, $data['email']);
    if ($tmpUser->id <= 0) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorUserNotFound') . '">';
        exit;
    }

    $tmpSignatory = new SaturneSignature($db, $moduleNameLowerCase, $attendanceSheet->element);
    $tmpSignatory->element_id     = $tmpUser->id;
    $tmpSignatory->firstname      = $tmpUser->firstname;
    $tmpSignatory->lastname       = $tmpUser->lastname;
    $tmpSignatory->element_type   = 'user';
    $tmpSignatory->role           = '';
    $tmpSignatory->object_type    = $attendanceSheet->element;
    $tmpSignatory->fk_object      = $attendanceSheet->id;
    $tmpSignatory->module_name    = $moduleNameLowerCase;
    $tmpSignatory->status         = $tmpSignatory::STATUS_PENDING_SIGNATURE;
    $tmpSignatory->signature_url  = generate_random_id();

    $result = $tmpSignatory->create($user);
    if ($result < 0) {
        echo '<input type="hidden" id="error" value="' . $langs->transnoentities('Error') . '">';
        exit;
    }
    $signatory_id = $result;

    $action = 'send_email';
}

if ($action == 'send_email') {
    if (empty($signatory_id)) {
        $signatory_id = GETPOSTINT('signatory_id');
    }

    $signatory->fetch($signatory_id);
    if ($signatory->id > 0) {
        require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

        $objectsMetadata[$objectType]['object']->fetch($id);

        $tmpUser = new User($db);
        $tmpUser->fetch($signatory->element_id);

        $from = $conf->global->MAIN_MAIL_EMAIL_FROM;

        // Make substitution in email content
        $substitutionarray                              = getCommonSubstitutionArray($langs, 0, null, $objectsMetadata[$objectType]['object']);
        $substitutionarray['__OBJECT_ELEMENT__']        = dol_strtolower($langs->transnoentities(ucfirst($objectsMetadata[$objectType]['object']->element)));
        $substitutionarray['__REF__']                   = $objectsMetadata[$objectType]['object']->ref;
        $signatoryLink                                  = dol_buildpath('/custom/doliletter/public/spread/add_spread.php', 3) . '?sign=' . $signatory->signature_url . '&id=' . $objectsMetadata[$objectType]['object']->id . '&object_type=' . $objectType;
        $substitutionarray['__SATURNE_SIGNATORY_URL__'] = '<a href=' . $signatoryLink . ' target="_blank">' . $langs->transnoentities('SignatureEmailURL') . '</a>';
        complete_substitutions_array($substitutionarray, $langs, $objectsMetadata[$objectType]['object'], $parameters);

        $result  = $saturneMail->fetch(getDolGlobalInt('DOLILETTER_EMAIL_TEMPLATE_SPREAD'));
        $subject = $result > 0 ? $saturneMail->topic : $langs->transnoentities('EmailSpreadTopic');
        $message = $result > 0 ? $saturneMail->content : $langs->transnoentities('EmailSpreadContent');
        // A signatory registered from the public page has no Dolibarr user: their email is on the signature itself
        $sendto  = !empty($tmpUser->email) ? $tmpUser->email : $signatory->email;

        $subject = make_substitutions($subject, $substitutionarray);
        $message = make_substitutions($message, $substitutionarray);

        // Create form object
        // Send mail (substitutionarray must be done just before this)
        $mailfile = new CMailFile($subject, $sendto, $from, $message, [], [], [], '', '', 0, -1, '', '', '', '', 'mail');
        if ($mailfile->error) {
            setEventMessages($mailfile->error, $mailfile->errors, 'errors');
        } elseif (!empty($conf->global->MAIN_MAIL_SMTPS_ID) || $conf->global->SATURNE_USE_ALL_EMAIL_MODE > 0) {
            $result = $mailfile->sendfile();
            if ($result) {
                $signatory->last_email_sent_date = dol_now();
                $signatory->update($user, true);
                $signatory->setPending($user, false);
                echo '<input type="hidden" id="success" value="' . $langs->transnoentities('SendEmailAt', dol_escape_htmltag($sendto)) . '">';
                exit;
            } else {
                echo '<input type="hidden" id="error" value="' . $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto)) . '">';
                exit;
            }
        }
    }
}

if ($action == 'login') {
    $url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $url .= "://".$_SERVER['HTTP_HOST'] . $_SERVER["PHP_SELF"] . '?' . http_build_query(array_filter($_GET, fn($item) => $item !== 'login'));
    setcookie(
        "doliletter_login_backtopage",
        $url,
        time() + (60 * 5),
        "/",
        "",
    );
    header("Location: " . dol_buildpath('', 3));
}

$ecmFiles->fetchAll('', '', 0, 0, 't.share:isnot:null');
$linkedFiles = [];
if (is_array($ecmFiles->lines) && !empty($ecmFiles->lines)) {
    $linkedFiles = array_filter($ecmFiles->lines, function ($ecmFilesLine) use ($objectType, $id, $objectsMetadata, $ecmFiles) {

        $objectType = $objectsMetadata[$objectType]['table_element'];

        $ecmFilesLine->table_element = $ecmFiles->table_element;
        $ecmFilesLine->fetch_optionals();

        return $ecmFilesLine->src_object_type == $objectType && $ecmFilesLine->src_object_id == $id;
    });
}
$linkedFilesFavorite = array_filter($linkedFiles, function ($ecmFilesLine) {
    return $ecmFilesLine->array_options['options_favorite'] == 1;
});
$linkedFiles = array_filter($linkedFiles, function ($ecmFilesLine) {
    return $ecmFilesLine->array_options['options_favorite'] != 1;
});

$link  = new Link($db);
$linkedLinks = [];
$link->fetchAll($linkedLinks, $objectType, $id);
array_walk($linkedLinks, fn ($linkedItem) => $linkedItem->fetch_optionals());
$linkedLinksFavorite = array_filter($linkedLinks, function ($linkedItem) {
    return $linkedItem->array_options['options_favorite'] == 1;
});
$linkedLinks = array_filter($linkedLinks, function ($linkedItem) {
    return $linkedItem->array_options['options_favorite'] != 1;
});


$signSignatory = null;
if (!empty($sign)) {
    $tmpSignatory = new DoliletterSpreadSignature($db);
    $directSignatoryId = $tmpSignatory->fetch(0, '', ' AND t.signature_url = "' . $sign . '"');
    if ($tmpSignatory->id > 0) {
        $signSignatory = $tmpSignatory; // Single-person view: only this signatory's signature + certification photos
    }
    if (!empty($tmpSignatory->signature)) {
        $directSignatoryId = 0;
    }
}

$signatories = $signatory->fetchSignatory('', $attendanceSheet->id ?? 0, $attendanceSheet->element);
if ($signatories <= 0) {
    $signatories = [];
} elseif (is_array($signatories)) {
    $signatories = current($signatories);
}

// Certification answers of each signatory: uploaded photo or "not concerned" declaration
$ppCertificationStates   = [];
$ppPendingCertifications = [];
if ($isPreventionPlan && !empty($ppCertifications)) {
    $certificationSignatories = $signatories;
    if (!empty($signSignatory)) {
        $certificationSignatories[$signSignatory->id] = $signSignatory;
    }

    foreach ($certificationSignatories as $certificationSignatory) {
        $ppCertificationStates[$certificationSignatory->id] = doliletter_spread_get_certification_states($ppCertifications, $certificationOptions, $ppCertBaseDir, $certificationSignatory->id, doliletter_spread_get_not_concerned_certifications($certificationSignatory));
    }

    if (!empty($signSignatory)) {
        $ppPendingCertifications = doliletter_spread_get_pending_certifications($ppCertificationStates[$signSignatory->id]);
    }
}

// Risks the identified visitor has already taken note of, so a reload does not undo their reading
$ppAcknowledgedRisks = (!empty($signSignatory)) ? doliletter_spread_get_acknowledged_risks($signSignatory) : [];
$ppPendingRisks      = ($isPreventionPlan && !empty($signSignatory)) ? doliletter_spread_get_pending_risks($ppRisks, $ppAcknowledgedRisks) : [];

/*
 * View
 */

$title  = $langs->trans('Signature');
$moreJS = ['/saturne/js/includes/signature-pad.min.js'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

saturne_header(0, '', $title, '', '', 0, 0, $moreJS, [], '', 'page-public-card');

require_once __DIR__ . '/../../core/tpl/spread/public_spread_view.tpl.php';

// Photo editor modal required by the Saturne media blocks (mediaBlock.js -> saturne.photoEditor.openFile)
if ($isPreventionPlan) {
    include dol_buildpath('/saturne/core/tpl/medias/photo_editor_modal.tpl.php');
}

llxFooter('', 'public');
$db->close();