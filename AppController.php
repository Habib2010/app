<?php

App::uses('Controller', 'Controller');

/**
 * Application controller
 *
 * This file is the base controller of all other controllers
 *
 * PHP version 5
 *
 * @category Controllers
 * @package  Croogo
 * @version  1.0
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class AppController extends Controller {

    /**
     * Components
     *
     * @var array
     * @access public
     */
    public $components = array(
        'Croogo',
        'Security',
        'Acl',
        'Auth',
        'Session',
        'RequestHandler',
        'Scms'
    );
    protected $scmsLevelTeacherRoleId = 10;
    public $scmsClassNames = array('ZERO', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 90 => 'PLAY', 91 => 'NURSERY-I', 92 => 'NURSERY-II');

    /**
     * Helpers
     *
     * @var array
     * @access public
     */
    public $helpers = array(
        'Html',
        'Form',
        'Session',
        'Text',
        'Js',
        'Time',
        'Layout',
        'Custom',
        'Calendar',
    );

    /**
     * Models
     *
     * @var array
     * @access public
     */
    public $uses = array(
        'Block',
        'Link',
        'Setting',
        'Node',
        'SmsLog'
    );

    /**
     * Pagination
     */
    public $paginate = array(
        'limit' => 20,
    );

    /**
     * Cache pagination results
     *
     * @var boolean
     * @access public
     */
    public $usePaginationCache = true;

    /**
     * View
     *
     * @var string
     * @access public
     */
    public $viewClass = 'Theme';

    /**
     * Theme
     *
     * @var string
     * @access public
     */
    public $theme;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct($request = null, $response = null) {
        Croogo::applyHookProperties('Hook.controller_properties', $this);
        parent::__construct($request, $response);
    }

    /**
     * beforeFilter
     *
     * @return void
     * @throws MissingComponentException
     */
    public function beforeFilter() {
        parent::beforeFilter();
        $aclFilterComponent = Configure::read('Site.acl_plugin') . 'Filter';
        if (empty($this->{$aclFilterComponent})) {
            throw new MissingComponentException(array('class' => $aclFilterComponent));
        }

        //echo '>>>>>[2]>>>'; die();
        $this->{$aclFilterComponent}->auth();
        $this->RequestHandler->setContent('json', 'text/x-json');
        $this->Security->blackHoleCallback = 'securityError';
        $this->Security->requirePost('admin_delete');

        if (isset($this->request->params['admin'])) {
            $this->layout = 'admin';
        }

        if ($this->RequestHandler->isAjax()) {
            $this->layout = 'ajax';
        }

        if (Configure::read('Site.theme') && !isset($this->request->params['admin'])) {
            $this->theme = Configure::read('Site.theme');
        } elseif (Configure::read('Site.admin_theme') && isset($this->request->params['admin'])) {
            $this->theme = Configure::read('Site.admin_theme');
        }

        if (!isset($this->request->params['admin']) &&
                Configure::read('Site.status') == 0) {
            $this->layout = 'maintenance';
            $this->response->statusCode(503);
            $this->set('title_for_layout', __('Site down for maintenance'));
            $this->render('../Elements/blank');
        }

        if (isset($this->request->params['locale'])) {
            Configure::write('Config.language', $this->request->params['locale']);
        }
    }

    /**
     * afterFilter callback
     * Disable debug mode on JSON pages to prevent the script execution time to be appended to the page
     *
     * @see http://croogo.lighthouseapp.com/projects/32818/tickets/216
     * @return void
     */
    public function afterFilter() {
        parent::afterFilter();
        if (!empty($this->params['url']['ext']) && $this->params['url']['ext'] === 'json') {
            Configure::write('debug', 0);
        }
    }

    /**
     * blackHoleCallback for SecurityComponent
     *
     * @return void
     */
    public function securityError($type) {
        switch ($type) {
            case 'auth':
                break;
            case 'csrf':
                break;
            case 'get':
                break;
            case 'post':
                break;
            case 'put':
                break;
            case 'delete':
                break;
            default:
                break;
        }
        $this->set(compact('type'));
        $this->response = $this->render('../Errors/security');
        $this->response->statusCode(400);
        $this->response->send();
        $this->_stop();
        return false;
    }

    //================== COMMON FUNCTIONs ======================
    public function calcGradePoints($total, $subjectTotal, $default) {
        $total_In_100 = $subjectTotal ? round($total * 100 / $subjectTotal, 2) : 0; //Avoid division by ZERO;

        if ($total_In_100 >= 80)
            return array('A+', 5.00);
        elseif ($total_In_100 >= 70)
            return array('A', 4.00);
        elseif ($total_In_100 >= 60)
            return array('A-', 3.50);
        elseif ($total_In_100 >= 50)
            return array('B', 3.00);
        elseif ($total_In_100 >= 40)
            return array('C', 2.00);
        elseif ($total_In_100 >= 33)
            return array('D', 1.00);
        else
            return array('F', 0.00);

        return $default;
    }

    public function addOrdinalNumberSuffix($num) {
        if (!in_array(($num % 100), array(11, 12, 13))) {
            switch ($num % 10) {
                // Handle 1st, 2nd, 3rd
                case 1: return $num . 'ST';
                case 2: return $num . 'ND';
                case 3: return $num . 'RD';
            }
        }
        return $num . 'TH';
    }

    public function getStudentCourses($level_id = NULL, $group_id = NULL, $type = '%Optional%') {
        $opts = array(
            'CourseCycle.school_session_id' => date('Y')
        );

        if (!empty($type))
            $opts['Course.type LIKE'] = $type; //'Compulsory','Selective','Optional','Islam','Hindu','Christian'

        if (!empty($level_id))
            $opts['CourseCycle.level_id'] = $level_id;

        if (!empty($group_id)) {
            $opts['OR'] = array(
                array('CourseCycle.group_id' => $group_id),
                array('CourseCycle.group_id' => NULL)
            );
        }

        $courses = $this->CourseCycle->find('list', array(
            'conditions' => $opts,
            //'fields' => array('Course.id','Course.name'),
            'fields' => array('CourseCycle.course_id', 'Course.name'),
            'recursive' => 0
                ));

        return $courses;
    }

    public function setStudentFormAddEditVars($withCaps, $items = array(), $args = array()) {//$level_id=NULL,$section_id=NULL,$group_id=NULL,$type='%Optional%'
        $level_id = isset($args['level_id']) ? $args['level_id'] : NULL;
        $section_id = isset($args['section_id']) ? $args['section_id'] : NULL;
        $group_id = isset($args['group_id']) ? $args['group_id'] : NULL;
        $type = isset($args['type']) ? $args['type'] : '%Optional%';

        //Get non related Info:
        if (in_array('studentCourses', $items))
            $this->set('studentCourses', $this->getStudentCourses($level_id, $group_id, $type));

        if (in_array('groups', $items))
            $this->set('groups', $this->Group->find('list', array('conditions' => array('level_id' => $level_id))));

        if (in_array('levels', $items))
            $this->set('levels', $this->Level->find('list', (empty($withCaps) ? NULL : array(
                                'conditions' => array(
                                    'Level.id' => $level_id
                                ),
                                'recursive' => -1
                                    ))));

        if (in_array('sections', $items))
            $this->set('sections', $this->Section->find('list', array(
                        'conditions' =>
                        (array('Section.level_id' => $level_id) + (empty($withCaps) ? array() : array('Section.id' => $section_id))
                        ),
                        'recursive' => -1
                    )));

        if (in_array('shifts', $items))
            $this->set('shifts', $this->Shift->find('list')); //array(1=>'MORNING',2=>'DAY'));

        if (in_array('religions', $items))
            $this->set('religions', array(1 => 'Islam', 2 => 'Hindu', 3 => 'Christian', 4 => 'Buddhist'));
    }

    /* protected function limitCapabilities(&$level_id, &$section_id){
      $withCaps = false;
      $userInfo = $this->Session->read("Auth.User");
      if( $userInfo['role_id'] && !empty($userInfo['capabilities']) ){
      $limitCaps = @unserialize(trim($userInfo['capabilities']));
      if( !(empty($limitCaps['level']) || empty($limitCaps['section'])) ){
      $withCaps = true;
      $level_id = $limitCaps['level'];
      $section_id = $this->Section->field('id',array(
      'level_id'	=> $limitCaps['level'],
      'name'		=> $limitCaps['section']
      ));
      }
      }
      return $withCaps;
      } */

    protected function limitCapabilities(&$level_id, &$section_id) {
        $withCaps = false;
        $userInfo = $this->Session->read("Auth.User"); //array('id'=>67,'role_id'=>10);//

        if ($userInfo['role_id'] == $this->scmsLevelTeacherRoleId) {
            $limitCaps = $this->Employee->find('first', array(
                'conditions' => array(
                    'Employee.user_id' => $userInfo['id']
                ),
                'fields' => array('id', 'level_id', 'section_id')
                    ));
            if (!(empty($limitCaps) || empty($limitCaps['Employee']['level_id']) || empty($limitCaps['Employee']['section_id']))) {
                $withCaps = true;
                $level_id = $limitCaps['Employee']['level_id'];
                $section_id = $limitCaps['Employee']['section_id'];
            }
        }
        return $withCaps;
    }

    protected function sendSMS($type, $recipients, $args = array()) {
        $Scms = Configure::read("Scms");
        if ($Scms['cmp'] == 'ON') {
            if (empty($type) || !in_array($type, array('absent', 'halfAbsent', 'general', 'admission', 'result-publish', 'admission-result', 'seat-plan')) || empty($recipients) || !is_array($recipients))
                return false;

            $countSms = 0;

            $Scms = Configure::read("Scms");
            if (empty($Scms['sms_tag']))
                $Scms['sms_tag'] = 'SCMS';
            if (empty($Scms['sms_head_mob']))
                $Scms['sms_head_mob'] = '8801720556561';

            $smsFoot = '#' . $Scms['sms_tag'] . '@TechPlexus'; //'#DZS@eSoftArena';
            $headNumber = $Scms['sms_head_mob']; //'01725021999'; //TGBHS-SIR==>01718078772,DZS-SIR==>01725021999,DCSC-SIR==>01717013766;
            //echo APP_PATH.'plugins'.DS.'plugin1'.DS.'webroot'.DS.'php'.DS.'array_test.php<br />';
            require_once(CORE_PATH . 'nusoap-0.9.5' . DS . 'nusoap.php');
            //App::import('Vendor', 'nusoap', array('file'=>array('nusoap-0.9.5'.DS.'nusoap.php')));
//            $client = new nusoap_client("https://bmpws.robi.com.bd/ApacheGearWS/services/CMPWebServiceSoap?wsdl", true);
//            $params = array(
//                'Username' => 'esfl',
//                'Password' => 'T0henltdP1X@',
//                'From' => '8801847050241', //88018XXXXXXXX
//                    //'To'		=> '8801XXXXXXXXX,8801XXXXXXXXX',
//                    //'Message'	=> ''
//            );
            $client = new nusoap_client("https://user.mobireach.com.bd/index.php?r=sms/service", true);
            $params = array(
                'Username' => 'esfl',
                'Password' => 'PlexTech@14',
                'From' => '8801847050241', 
            );

            if ($type == 'general') {
                $params['Message'] = trim($args['msg']) . "\n" . $smsFoot;
                $recipients = array_map('trim', $recipients);
                $recipients = array_merge($recipients, array_filter(explode(',', $headNumber))); //DEBUG:: ?????????????????/
                //echo '++++++++++++TOTAL = '.count($recipients).'==========<br />';
                for ($i = 0, $limit = 90; $i < count($recipients); $i+=$limit) {
                    $recipients = preg_replace('/^[0]/', '88$0', $recipients);
                    $recpntPart = array_slice($recipients, $i, $limit, true);
                    $params['To'] = implode(',', $recpntPart);
                    $result = $client->call('SendTextMultiMessage', $params);
                    //echo '++++++++++++'.count($recpntPart).'=========='; pr($recpntPart);
                }
                //return count($result['SendTextMultiMessageResult']['ServiceClass']);
                $countSms = count($recipients);
                $this->saveSms($countSms, $params['Message'], $type);
            } elseif ($type == 'absent') {
                $countSms = count($recipients);
                $adminMsg = $args['level'] . '-' . $args['section'] . '-(total=' . $countSms . ')';
                //====Guardian's Copy======
                foreach ($recipients as $k => $student) {
                    $params['To'] = preg_replace('/^[0]/', '88$0', $student['Guardian']['mobile']);
                    $params['Message'] =
                            "Dear Parent,
Please be informed that " . strtoupper($student['Student']['name']) . " was ABSENT on " . date('j-M-y') . ". For enquiry please contact with Class Teacher.
$smsFoot";
                    // pr($params);
                    $result1 = $client->call('SendTextMessage', $params);
                }
                //die;
                //====Admin Notification====:
                $params['To'] = $headNumber;
                $params['Message'] =
                        "Dear Sir,
Please be informed that the absent SMS requests were sent for the class: " . $adminMsg . " on " . date('j-M-y \a\t h:i:s A') . "
$smsFoot";
                $result2 = $client->call('SendTextMultiMessage', $params);
                $cnt = array_filter(explode(',', $params['To']));
                $countSms+=count($cnt);
                $this->saveSms($countSms, $params['Message'], $type);
                //return $countSms;
            } elseif ($type == 'halfAbsent') {
                $countSms = count($recipients);
                $adminMsg = $args['level'] . '-' . $args['section'] . '-(total=' . $countSms . ')';
                //====Guardian's Copy======
                foreach ($recipients as $k => $student) {
                    $params['To'] = preg_replace('/^[0]/', '88$0', $student['Guardian']['mobile']);
                    $params['Message'] =
                            "Dear Parent,
Please be informed that " . strtoupper($student['Student']['name']) . " went away from school in the middle time at " . date('j-M-y') . "
$smsFoot";
                    //pr($params);
                    $result1 = $client->call('SendTextMessage', $params);
                }

                //====Admin Notification====:
                $params['To'] = $headNumber;
                $params['Message'] =
                        "Dear Sir,
Please be informed that the half present SMS requests were sent for the class: " . $adminMsg . " on " . date('j-M-y \a\t h:i:s A') . "
$smsFoot";
                $result2 = $client->call('SendTextMultiMessage', $params);
                $cnt = array_filter(explode(',', $params['To']));
                $countSms+=count($cnt);
                $this->saveSms($countSms, $params['Message'], $type);
                return $countSms;
            } elseif ($type == 'admission') {
                $params['To'] = preg_replace('/^[0]/', '88$0', $args['mobile']);
                $params['Message'] = ($recipients[0] == 'pmnt-verified') ?
                        "Dear Guardian,
Your payment is verified,
Name: " . strtoupper($args['name']) . "
Ref: " . strtoupper($args['ref']) . "
Roll: " . strtoupper($args['roll']) . "
trxId: " . $args['trxId'] . "
Class: " . $args['level'] . "
$smsFoot" :
                        "Dear Guardian,
Registration is successful,
Name: " . strtoupper($args['name']) . "
Ref: " . strtoupper($args['ref']) . "
Class: " . $args['level'] . "
Pay on bKash with this ref no." . " " . "
$smsFoot";
                $result = $client->call('SendTextMessage', $params);
                $countSms = 1;
            } elseif ($type == 'result-publish') {
                $countSms = count($recipients);

                //====Guardian's Copy======
                foreach ($recipients as $k => $student) {
                    //if($k >= 150 && $k <= 300) {
                    if ($student['StudentMerit']['gpa'] >= 5.00) {
                        $student['StudentMerit']['gpa'] = 5.00;
                    }
                    $params['To'] = preg_replace('/^[0]/', '88$0', $student['Guardian']['mobile']);
                    $params['Message'] =
                            "Exam: " . strtoupper($args['term-name']) . "
Name: " . strtoupper($student['Student']['name']) . "
SID: " . $student['Student']['sid'] . "
Class: " . $student['Level']['name'] . "-" . $student['Section']['name'] . (empty($student['Group']['name']) ? '' : '-(' . $student['Group']['name'] . ')') . "
Total: " . strtoupper($student['StudentMerit']['total']) . "
GPA: " . strtoupper($student['StudentMerit']['gpa']) . "
Grade: " . $student['StudentMerit']['grade'] . "
Merit: " . (empty($student['StudentMerit']['merit']) ? 'N/A' : $this->addOrdinalNumberSuffix($student['StudentMerit']['merit'])) . "
$smsFoot";
                    //pr("======\n".$params['Message']);
                    $result1 = $client->call('SendTextMessage', $params);
                    //}
//                    else {
//                        break;
//                    }
                }
//                pr($k); die;

                //====Admin Notification====:
                $params['To'] = $headNumber;
                $params['Message'] =
                        "Dear Sir,
Please be informed that the result pulish SMS requests were sent for the classes:
[" . implode(', ', $args['levels']) . "]
on " . date('j-M-y \a\t h:i:s A') . ".
$smsFoot";
                $result2 = $client->call('SendTextMultiMessage', $params);
                //pr("======\n".$params['Message']);
                $cnt = array_filter(explode(',', $params['To']));
                $countSms+=count($cnt);
                $this->saveSms($countSms, $params['Message'], $type);
            } elseif ($type == 'admission-result') {
                foreach ($recipients as $k => $recipient) {
                    if ($recipient['status'] <= 4) {
                        $params['To'] = preg_replace('/^[0]/', '88$0', $recipient['mobile']);
                        $params['Message'] =
                                "Congratulation ! Applicant Passed Successfully" . "
Name: " . strtoupper($recipient['name']) . "
Roll: " . $recipient['roll'] . "
Ref: " . $recipient['ref'] . "
Merit Position: " . strtoupper($recipient['merit']) . "
$smsFoot";
                        $result1 = $client->call('SendTextMessage', $params);
                    }
                }
            } elseif ($type == 'seat-plan') {
                foreach ($recipients as $k => $recipient) {
                    // pr($recipient); die;
                    $params['To'] = preg_replace('/^[0]/', '88$0', $recipient['Admission']['mobile']);
                    $params['Message'] =
                            "Applicant's seat plan: " . "
Name: " . strtoupper($recipient['Admission']['name']) . "
Roll: " . $recipient['Admission']['roll'] . "
Ref: " . $recipient['Admission']['ref'] . "
Room: " . $recipient['Admission']['room'] . "
Location: " . strtoupper($recipient['Admission']['location']) . "
$smsFoot";
                    $result1 = $client->call('SendTextMessage', $params);
                }
            }

            $this->increaseSmsCountBy($countSms);
            return $countSms;
            /*
              Array
              (
              [SendTextMultiMessageResult] => Array
              (
              [ServiceClass] => Array
              (
              [0] => Array
              (
              [MessageId] => 265981056
              [Status] => 0
              [StatusText] => N/A
              [ErrorCode] => 0
              [ErrorText] => N/A
              [SMSCount] => 1
              [CurrentCredit] => 9997
              )

              [1] => Array
              (
              [MessageId] => 265981057
              [Status] => 0
              [StatusText] => N/A
              [ErrorCode] => 0
              [ErrorText] => N/A
              [SMSCount] => 1
              [CurrentCredit] => 9997
              )

              [2] => Array
              (
              [MessageId] => 265981058
              [Status] => 0
              [StatusText] => N/A
              [ErrorCode] => 0
              [ErrorText] => N/A
              [SMSCount] => 1
              [CurrentCredit] => 9997
              )

              [3] => Array
              (
              [MessageId] => 265981060
              [Status] => 0
              [StatusText] => N/A
              [ErrorCode] => 0
              [ErrorText] => N/A
              [SMSCount] => 1
              [CurrentCredit] => 9997
              )

              )

              )

              ) */
        } else {
            echo 'SMS Service is Unavailable';
        }
    }

    private function saveSms($total, $message, $type) {
        $date = date('Y-m-d');
        $insertQuery = "INSERT INTO sms_logs " . "(type,body,total,date) " . "VALUES('$type','$message','$total','$date')";
        if ($insertQuery) {
            $this->SmsLog->query($insertQuery);
        }
    }

    protected function increaseSmsCountBy($by) {
        $Scms = Configure::read("Scms");
        if (empty($Scms['sms_count_bill']) || !is_numeric($Scms['sms_count_bill']))
            $Scms['sms_count_bill'] = 0;
        if (empty($Scms['sms_count_life']) || !is_numeric($Scms['sms_count_life']))
            $Scms['sms_count_life'] = 0;

        $this->Setting->create();
        return $this->Setting->saveMany(array(
                    array('id' => 54, 'value' => ($Scms['sms_count_bill']+=$by)),
                    array('id' => 55, 'value' => ($Scms['sms_count_life']+=$by))
                ));
    }

    /* function _execute($sql){
      $res = mysql_query($sql, $this->connection);
      //re-connect and re-try the quey if connection was lost due to db timeoout
      if( !$res && (2006 == mysql_errno($this->connection) || 2013 == mysql_errno($this->connection)) && $this->connect()){
      return mysql_query($sql, $this->connection);
      }
      return $res;
      } */

    protected function chk_bKash($queryStr, $args = array()) {

        if (!empty($queryStr) && !empty($args['qType']) && in_array($args['qType'], array('trxid', 'reference', 'timestamp'))) {


            //====== D E B U G =======
            /* $response = $response = array('transaction'=>array(
              'amount' => 5, ///
              'counter' => 1,
              'currency' => 'BDT',
              'receiver' => '01720556561',
              'reference' => $args['ref'], ///
              'sender' => '01722856004',
              'service' => 'Payment',
              'trxId' => $queryStr,///
              'trxStatus' => '0000',
              'trxTimestamp' => date('Y-m-d H:i:s')
              ));
              return $this->array_to_object($response); */
            //==========================


            $urlPart = array(
                'trxid' => 'sendmsg',
                'reference' => 'refmsg',
                //'lastpollingtime'	=> 'periodicpullmsg', //GET Method
                'timestamp' => 'periodicpullmsg' //POST Method
            );

            $data = array(
                'user' => 'TECHPLEXUS',
                'pass' => 't3cH9L3Xu5247',
                'msisdn' => '01705806080',
                $args['qType'] => $queryStr //'513132201', ['trxid'||'reference']
            );

            /* $data = array(
              'user'				=> 'ESOFTARENA',
              'pass'				=> 'ad0b3tng0',
              'msisdn'			=> '01720556561',
              'lastpollingtime'	=> date('Y-m-d-Hi') //'2012-04-25-1920'
              ); */

            //http://www.bkashcluster.com:9080/dreamwave/merchant/trxcheck/periodicpullmsg?user=ESOFTARENA&pass=ad0b3tng0&msisdn=01720556561&lastpollingtime=2013-11-20-2330

            $url = "https://www.bkashcluster.com:9081/dreamwave/merchant/trxcheck/" . $urlPart[$args['qType']];
            $content = json_encode($data);
            //echo '???????????'.$content;
            //return false;
            //http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/
            //https://www.net24.co.nz/kb/article/AA-00246/

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            //curl_setopt($this->curlHandle, CURLOPT_USERPWD,"{$this->userName}:{$this->userPass}");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //while https !!!!!
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
            //curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 100);
            //curl_setopt($curl, CURLOPT_TIMEOUT, 84600);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($content),
                'Connection: Keep-Alive', //Added Later
                'Keep-Alive: 30' //Added Later
            ));
            $json_response = curl_exec($curl);
            //echo '>>>>>>>>>>>>'.$json_response;


            /* // Check if any error occurred
              if( !curl_errno($curl) ){
              $info = curl_getinfo($curl);
              echo 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'];
              } */

            // Close handle
            curl_close($curl);

            if (!empty($json_response))
                return json_decode($json_response);

            /* $response = array();
              $queryId = (int)$queryStr;
              switch($queryId){
              case 511132201:
              $response = array('transaction'=>array(
              'amount' => 5, ///
              'counter' => 1,
              'currency' => 'BDT',
              'receiver' => '01720556561',
              'reference' => 'DZ14B75392', ///
              'sender' => '01722856004',
              'service' => 'Payment',
              'trxId' => '513132201',///
              'trxStatus' => '0000',
              'trxTimestamp' => date('Y-m-d H:i:s')
              ));
              break;
              default:
              $response = array('transaction'=>array(
              'amount' => 5,
              'counter' => 1,
              'currency' => 'BDT',
              'receiver' => '01720556561',
              'reference' => $queryStr,
              'sender' => '01722856004',
              'service' => 'Payment',
              'trxId' => '513132201',
              'trxStatus' => '0000',
              'trxTimestamp' => date('Y-m-d H:i:s')
              ));
              break;
              }

              return $this->array_to_object($response); */
        }

        return false;

        /*
          stdClass Object(
          [transaction] => stdClass Object
          (
          [amount] => 5
          [counter] => 1
          [currency] => BDT
          [receiver] => 01720556561
          [reference] => DZSA140001
          [sender] => 01722856004
          [service] => Payment
          [trxId] => 513132201
          [trxStatus] => 0000
          [trxTimestamp] => 2013-11-10T18:29:55+06:00
          )
          ) */
    }

    function array_to_object($array) {
        $obj = new stdClass;
        foreach ($array as $k => $v) {
            if (strlen($k)) {
                if (is_array($v)) {
                    $obj->{$k} = $this->array_to_object($v); //RECURSION
                } else {
                    $obj->{$k} = $v;
                }
            }
        }
        return $obj;
    }

    function get_bKash_statusMSG($st) {
        $msg = '';
        switch ($st) {
            case '0000' :
                $msg = 'trxID is valid and transaction is successful.'; //Transaction Successful.
                break;
            case '0010':
            case '0011':
                $msg = 'trxID is valid but transaction is in pending state. Transaction Pending.';
                break;
            case '0100':
                $msg = 'trxID is valid but transaction has been reversed. Transaction Reversed.';
                break;
            case '0111':
                $msg = 'trxID is valid but transaction has failed. Transaction Failure.';
                break;
            case '1001':
                $msg = 'Invalid MSISDN input. Try with correct mobile no. Format Error.';
                break;
            case '1002':
                $msg = 'Invalid trxID, it does not exist. Invalid Reference.';
                break;
            case '1003':
                $msg = 'Access denied. Username or Password is incorrect. Authorization Error.';
                break;
            case '1004':
                //$msg = 'Access denied. trxID is not related to this username. Authorization Error.';
                //$msg = '????? ???????? ????? ??? ??????? ???? ????? ????? ????? Transaction ID verification ??? ????? ???? ??? ?????? ??? ???? ??????? ??????';
                $msg = 'Make sure that your TrxId is correct. Otherwise, your Admit Card is processing�. Please try after five minutes.';
                break;
            case '9999':
                $msg = 'Could not process request. System Error.';
            default:
                $msg = '--!!--';
                break;
        }

        return $msg;
    }

}
