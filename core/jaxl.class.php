<?php
/* Jaxl (Jabber XMPP Library)
 *
 * Copyright (c) 2009-2010, Abhinav Singh <me@abhinavsingh.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Abhinav Singh nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRIC
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

    /*
     * Library Metainfo
    */
    declare(ticks=1);
    define('JAXL_NAME', 'Jaxl :: Jabber XMPP Client Library');
    define('JAXL_VERSION', '2.1.2');

    /*
     * autoLoad method for Jaxl library and apps
    */
    function jaxl_require($classNames, $jaxl=false) {
        static $included = array();
        $tagMap = array(
            // core classes
            'JAXL' => '/core/jaxl.class.php',
            'JAXLCron' => '/core/jaxl.cron.php',
            'JAXLog' => '/core/jaxl.logger.php',
            'JAXLDb' => '/core/jaxl.mdbm.php',
            'JAXLXml' => '/core/jaxl.parser.php',
            'JAXLPlugin' => '/core/jaxl.plugin.php',
            'JAXLUtil' => '/core/jaxl.util.php',
            'XML' => '/core/jaxl.xml.php',  
            // xmpp classes
            'XMPP' => '/xmpp/xmpp.class.php',
            'XMPPGet' => '/xmpp/xmpp.get.php',
            'XMPPSend' => '/xmpp/xmpp.send.php',
            'XMPPAuth' => '/xmpp/xmpp.auth.php'
        );
        
        if(!is_array($classNames)) $classNames = array('0'=>$classNames);
        foreach($classNames as $key => $className) {
            $xep = substr($className, 4, 4);
            if(substr($className, 0, 4) == 'JAXL'
            && is_numeric($xep)
            ) { // is XEP
                if(!isset($included[$className])) {
                    require_once JAXL_BASE_PATH.'/xep/jaxl.'.$xep.'.php';
                    $included[$className] = true;
                }
                call_user_func(array('JAXL'.$xep, 'init'), $jaxl);
            } // is Core file
            else if(isset($tagMap[$className])) {
                require_once JAXL_BASE_PATH.$tagMap[$className];
                $included[$className] = true;
            }
        }
        return;
    }

    /*
     * include core, xmpp base
     * (basic requirements for every Jaxl instance)
    */
    jaxl_require(array(
        'JAXLog',
        'JAXLUtil',
        'JAXLPlugin',
        'JAXLCron',
        'XML',
        'XMPP',
    ));

    /*
     * Jaxl Core Class extending Base XMPP Class
    */
    class JAXL extends XMPP {

        /* Custom config passed to constructor */
        var $config = array();

        /* User account related parameters */
        var $user = false;
        var $pass = false;
        var $host = false;
        var $port = 5222;
        var $jid = false;
        var $domain = false;
        var $resource = false;
        var $component = false;

        /* Core working parameters */
        var $logLevel = 1;
        var $logRotate = false;
        var $logPath = '/var/log/jaxl.log';
        var $pidPath = '/var/run/jaxl.pid';
        var $sigh = true;
        var $pid = false;
        var $mode = false;
        var $action = false;
        var $authType = false;
        var $dumpStat = 300;

        /* Support feature and meta about Jaxl instance */
        var $features = array();
        var $category = 'client';
        var $type = 'bot';
        var $lang = 'en';
        var $name = 'Jaxl :: Jabber XMPP Client Library';

        /*
         * Core constructor
        */
        function __construct($config=array()) {
            $this->mode = (PHP_SAPI == "cli") ? PHP_SAPI : "cgi";
            $this->pid = getmypid();
            $this->config = $config;

            /* Mandatory params to be supplied either by jaxl.ini constants or constructor $config array */ 
            $this->user = isset($config['user']) ? $config['user'] : JAXL_USER_NAME;
            $this->pass = isset($config['pass']) ? $config['pass'] : JAXL_USER_PASS;
            $this->domain = isset($config['domain']) ? $config['domain'] : JAXL_HOST_DOMAIN;
            
            /* Optional params if not configured using jaxl.ini or $config take default values */
            $this->port = isset($config['port']) ? $config['port'] : (constant(JAXL_HOST_PORT) == null ? $this->port : JAXL_HOST_PORT);
            $this->host = isset($config['host']) ? $config['host'] : (constant(JAXL_HOST_NAME) == null ? $this->domain : JAXL_HOST_NAME);
            $this->resource = isset($config['resource']) ? $config['resource'] : (constant(JAXL_USER_RESC) == null ? "jaxl.".time() : JAXL_USER_RESC);
            $this->logLevel = isset($config['logLevel']) ? $config['logLevel'] : (constant(JAXL_LOG_LEVEL) == null ? $this->logLevel : JAXL_LOG_LEVEL);
            $this->logRotate = isset($config['logRotate']) ? $config['logRotate'] : (constant(JAXL_LOG_ROTATE) == null ? $this->logRotate : JAXL_LOG_ROTATE);
            $this->logPath = isset($config['logPath']) ? $config['logPath'] : (constant(JAXL_LOG_PATH) == null ? $this->logPath : JAXL_LOG_PATH);
            $this->pidPath = isset($config['pidPath']) ? $config['pidPath'] : (constant(JAXL_PID_PATH) == null ? $this->pidPath : JAXL_PID_PATH);

            /* Optional params which can be configured only via constructor $config */
            $this->sigh = isset($config['sigh']) ? $config['sigh'] : true;
            $this->dumpStat = isset($config['dumpStat']) ? $config['dumpStat'] : 300;
           
            /* Mandatory param while working with XEP-0115 or XEP-0206 */
            $this->component = isset($config['component']) ? $config['component'] : JAXL_COMPONENT_HOST;
            $this->boshHost = isset($config['boshHost']) ? $config['boshHost'] : JAXL_BOSH_HOST;
            $this->boshPort = isset($config['boshPort']) ? $config['boshPort'] : JAXL_BOSH_PORT;
            $this->boshSuffix = isset($config['boshSuffix']) ? $config['boshSuffix'] : JAXL_BOSH_SUFFIX;

            /* Configure instance for platforms and call parent construct */
            $this->configure($config);
            parent::__construct($config);
            $this->xml = new XML();
            
            /* Initialize JAXLCron and register instance cron jobs */
            JAXLCron::init();
            if($this->dumpStat) JAXLCron::add(array($this, 'dumpStat'), $this->dumpStat);
            if($this->logRotate) JAXLCron::add(array('JAXLog', 'logRotate'), $this->logRotate);
            
            // include service discovery XEP-0030, recommended for every XMPP entity
            jaxl_require('JAXL0030', $this);
        }
        
        /*
         * Configures Jaxl instance to run across various systems
        */
        protected function configure($config) {
            if(!JAXLUtil::isWin() && JAXLUtil::pcntlEnabled() && $this->sigh) {
                pcntl_signal(SIGTERM, array($this, "shutdown"));
                pcntl_signal(SIGINT, array($this, "shutdown"));
                $this->log("Registering shutdown for SIGH Terms ...", 1);
            }
            
            if(JAXLUtil::sslEnabled()) {
                $this->log("Openssl enabled ...", 1);
            }
            
            if($this->mode == "cli") {
                if(!function_exists('fsockopen')) die("Jaxl requires fsockopen method ...");  
                if(@is_writable($this->pidPath)) file_put_contents($this->pidPath, $this->pid);
            }
            
            if($this->mode == "cgi") {
                if(!function_exists('curl_init')) die("Jaxl requires curl_init method ...");
            }
        }
       
        /*
         * Periodically dumps jaxl instance usage stats
        */
        function dumpStat() {
            $this->log("Memory usage: ".round(memory_get_usage()/pow(1024,2), 2)." Mb, peak: ".round(memory_get_peak_usage()/pow(1024,2), 2)." Mb", 0);
        }
        
        /*
         * Magic method for calling XEP methods using JAXL instance
        */
        function __call($xep, $param) {
            $method = array_shift($param);
            array_unshift($param, $this);
            if(substr($xep, 0, 4) == 'JAXL') {
                $xep = substr($xep, 4, 4);
                if(is_numeric($xep)
                && class_exists('JAXL'.$xep)
                ) { return call_user_func_array(array('JAXL'.$xep, $method), $param); }
            }
        } 
       
        /************************************/
        /*** User space available methods ***/
        /************************************/
        function shutdown($signal) {
            $this->log("Jaxl Shutting down ...", 0);
            JAXLPlugin::execute('jaxl_pre_shutdown', $signal, $this);
            
            if($this->stream) $this->endStream();
            $this->stream = false;
        }
        
        function auth($type) {
            $this->authType = $type;
            return XMPPSend::startAuth($this);
        }
        
        function setStatus($status=false, $show=false, $priority=false, $caps=false) {
            $child = array();
            $child['status'] = ($status === false ? 'Online using Jaxl library http://code.google.com/p/jaxl' : $status);
            $child['show'] = ($show === false ? 'chat' : $show);
            $child['priority'] = ($priority === false ? 1 : $priority);
            if($caps) $child['payload'] = $this->JAXL0115('getCaps', $this->features);
            return XMPPSend::presence($this, false, false, $child, false);
        }
        
        function subscribe($toJid) {
            return XMPPSend::presence($this, $toJid, false, false, 'subscribe');
        }
        
        function subscribed($toJid) {
            return XMPPSend::presence($this, $toJid, false, false, 'subscribed');
        }
        
        function unsubscribe($toJid) {
            return XMPPSend::presence($this, $toJid, false, false, 'unsubscribe');
        }
        
        function unsubscribed($toJid) {
            return XMPPSend::presence($this, $toJid, false, false, 'unsubscribed');
        }
        
        function getRosterList($callback=false) {
            $payload = '<query xmlns="jabber:iq:roster"/>';
            return XMPPSend::iq($this, "get", $payload, false, $this->jid, $callback);
        }
        
        function addRoster($jid, $group, $name=false) {
            $payload = '<query xmlns="jabber:iq:roster">';
            $payload .= '<item jid="'.$jid.'"';
            if($name) $payload .= ' name="'.$name.'"';
            $payload .= '>';    
            $payload .= '<group>'.$group.'</group>';
            $payload .= '</item>';
            $payload .= '</query>';
            return XMPPSend::iq($this, "set", $payload, false, $this->jid, false);
        }
        
        function updateRoster($jid, $group, $name=false, $subscription=false) {
            $payload = '<query xmlns="jabber:iq:roster">';
            $payload .= '<item jid="'.$jid.'"';
            if($name) $payload .= ' name="'.$name.'"';
            if($subscription) $payload .= ' subscription="'.$subscription.'"';
            $payload .= '>';
            $payload .= '<group>'.$group.'</group>';
            $payload .= '</item>';
            $payload .= '</query>';
            return XMPPSend::iq($this, "set", $payload, false, $this->jid, false);
        }
        
        function deleteRoster($jid) {
            $payload = '<query xmlns="jabber:iq:roster">';
            $payload .= '<item jid="'.$jid.'" subscription="remove">';
            $payload .= '</item>';
            $payload .= '</query>';
            return XMPPSend::iq($this, "set", $payload, false, $this->jid, false);
        }
        
        function sendMessage($to, $message, $from=false, $type='chat') {
            $child = array();
            $child['body'] = $message;
            return XMPPSend::message($this, $to, $from, $child, $type);
        }
        
        function sendMessages($to, $from, $child, $type) {
            return XMPPSend::message($this, $to, $from, $child, $type);
        }

        function sendPresence($to, $from, $child, $type) {
           XMPPSend::presence($this, $to, $from, $child, $type);
        }

        function log($log, $level) {
            JAXLog::log($log, $level, $this);
        }

        function requires($class) {
            jaxl_require($class, $this);
        }

    }

?>
