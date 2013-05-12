<?php
/**
 * pregister plugin
 * registers users by means of a confirmation link 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Myron Turner<turnermm02@shaw.ca>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_preregister extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    private $metaFn;
    function register(&$controller){
            $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'BEFORE', $this, 'update_register_form');
            $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'allow_preregister_check');
            $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'process_preregister_check');     
     }
    
    function __construct() {       
       $metafile= 'preregister:db';
       $this->metaFn = metaFN($metafile,'.ser');
    }
        
   function allow_preregister_check(&$event, $param) {
    $act = $this->_act_clean($event->data);    
    if($act != 'preregistercheck') return; 
    $event->preventDefault();
  }
 
    function process_preregister_check(&$event, $param) {
         global $ACT;
         if($ACT != 'preregistercheck') return; 
         if($_GET && $_GET['prereg']) {
             echo "Registering: " . $_GET['prereg'];
             $this->process_registration($_GET['prereg']);
             $event->preventDefault();
             return;
         }

        $event->preventDefault();
        
         if($this->is_user($_REQUEST['login']))  return;  // name already taken
         
         $failed = false;
         if(!isset($_REQUEST['card'])) return;
         foreach($_REQUEST['card'] as $card) {          
             if(strpos($_REQUEST['sel'],$card) === false) {
                 $failed = true;
                 break;                
             }
          }
         if($failed) {
             echo "<h4>Your Selections do not match the cards; please try again.</h4>";
             return;
        }

        $t = time();
        $index = md5($t);
        $url = DOKU_URL . 'doku.php?' . $_REQUEST['id']. '&do=preregistercheck&prereg='. $index;    
        
        if($this->send_link($_REQUEST['email'], $url) ) {
            echo "An email with a confirmation link has been sent to your email address.  Either click on that link or paste it "
              . " into your browser.  You will then be registered and will receive your password. ";
        }
        else echo "A problem occurred in sending your confirmation link to your email address. You might try again later.";
          
        
          $data = unserialize(io_readFile($this->metaFn,false)); 
          if(!$data) $data = array();          
          $data[$index] = $_POST;
          $data[$index]['savetime'] = $t;
          io_saveFile($this->metaFn,serialize($data));
    }
  
    function update_register_form(&$event, $param) {    
        if($_SERVER['REMOTE_USER']){
            return;
        }
      
        $event->data->_hidden['save'] = 0;
        $event->data->_hidden['do'] = 'preregistercheck';
 
        for($i=0; $i <count($event->data->_content); $i++) {
            if($event->data->_content[$i]['type'] == 'submit') {
                $event->data->_content[$i]['value'] = 'Submit';
                break; 
            }
        }    
        $pos = $event->data->findElementByAttribute('type','submit');       
        if(!$pos) return; // no button -> source view mode
        $cards = $this-> get_cards();
        $sel ="";
        $out = $this->format_cards($cards,$sel);        
        $event->data->_hidden['sel'] = implode("",$sel);      
        $event->data->insertElement($pos++,$out);
    }
    

    function process_registration($index) {

           $data = unserialize(io_readFile($this->metaFn,false)); 
           $post = $data[$index];
           $post['save'] = 1;
           $_POST= array_merge($post, array());
           if(register()) {
              unset($data[$index]);
              io_saveFile($this->metaFn,serialize($data));
           }
          
    }

 
    /**
     * Pre-Sanitize the action command
     *
     * Similar to act_clean in action.php but simplified and without
     * error messages
     */
    function _act_clean($act){
         // check if the action was given as array key
         if(is_array($act)){
           list($act) = array_keys($act);
         }

         //remove all bad chars
         $act = strtolower($act);
         $act = preg_replace('/[^a-z_]+/','',$act);

         return $act;
     }
     
    function format_cards($cards,&$sel) {
        $sel = array_slice($cards,0,3);
        shuffle($cards);
        $new_row = (int)(count($cards)/2);
        $out = $sel[0] . '&nbsp;&nbsp;' . $sel[1] . '&nbsp;&nbsp;' . $sel[2] . '<br />';
        $out = str_replace(array('H','S','D','C'),array('&#9829;','&#9824;','&#9830;','&#9827;'),$out);
        $out = "Check off the matching cards<br />" . $out;
        $out .= '<center><table cellspacing="2"><tr>';
        $i=0;
        foreach($cards as $card) {
            $i++;
            $name = 'card[]'; 
            
            $out .= '<td>' . str_replace(array('H','S','D','C'),array('&#9829;','&#9824;','&#9830;','&#9827;'),$card)
                    . " <input type = 'checkbox' name = '$name' value = '$card' /></td>"; 
            if($i==$new_row) $out .='</tr><tr>';        
        }
        $out .= '</tr></table></center>';
        return $out;
   }    
   
    function get_cards() {       
         for($i=1; $i<14; $i++) {
            $c = $i;
            if($i == 1) {
              $c='A';
             }
            if($i == 11) {
              $c='J';
            }            
             if($c == 12) {
              $c='Q';
            }            
            if($i == 13) {
              $c='K';
            }            
            $hearts[] = $c . "H";
            $clubs[] = $c. "C";
            $diamonds[] = $c ."D";
            $spades[] =  $c."S";
         }
     $deck = array_merge($hearts,$clubs, $diamonds, $spades); 
     shuffle($deck);
      return array_slice($deck,0,10);
    }
    
    
    function send_link($email, $url) {  
        
        if(!mail_isvalid($email)) {
             msg("Invalid email address: $email");
             return false; 
        } 
     
        global $conf;
        $text = "Either click on this link or paste it into your browser to complete your registration:\n\n";
        $text .= $url;
        $text .= "\n\n";      
        return mail_send($email, "Confirmation Link from: " . $conf['title'],$text, "root@localhost");
        
}

function is_user($uname) {
    global $config_cascade;
    $authusers = $config_cascade['plainauth.users']['default'];
    if(!is_readable($authusers)) return false;
   
    $users = file($authusers);
    $uname = utf8_strtolower($uname);
    foreach($users as $line) {
        $line = trim($line);
        if($line{0} == '#') continue;
        list($user,$rest) = preg_split('/:/',$line,2);
        if($uname == $user) {
           msg("userid $user is already in use",-1);
           return true;
        }      
    }
    return false;
 }   
 
    function write_debug($what, $toscreen=true, $tofile=false) {
    
        if(is_array($what)) {
            $what = print_r($what,true);
        }
        if($toscreen) {
           return "<pre>$what</pre>" ;
        }
        if(!$tofile) {
           return "";
        }        


       $handle=fopen('preregister.txt','a');
        fwrite($handle, "$what\n");
        fclose($handle);
     }   
}
