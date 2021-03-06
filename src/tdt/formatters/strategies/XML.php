<?php
/**
 * This file contains the Xml formatter.
 * @copyright (C) 2011,2013 by iRail vzw/asbl, OKFN Belgium
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@iRail.be>
 * @author Pieter Colpaert   <pieter@iRail.be>
 */

namespace tdt\formatters\strategies;

define("NUMBER_TAG_PREFIX","_");

class XML extends \tdt\formatters\AStrategy{
    //make a stack of array information, always work on the last one
    //for nested array support
    private $stack = array();
    private $arrayindices = array();
    private $currentarrayindex = -1;

    public function __construct($rootname,$objectToPrint){
        parent::__construct($rootname,$objectToPrint);
    }

    public function printHeader(){
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: text/xml;charset=UTF-8");
    }

    public function printBody(){
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";

        $rootname = $this->rootname;

        if(empty($this->objectToPrint->$rootname)){
            // Because the rootname plays a prominent role in XML, we need to be sure
            // it's a valid rootname. If not, we take the first datamember as rootname.
            $entry = get_object_vars($this->objectToPrint);
            $rootname = array_shift(array_keys($entry));
            $this->rootname = $rootname;
        }

        if(!isset($this->objectToPrint->$rootname)){
            $rootname = ucfirst($this->rootname);
            $this->rootname = $rootname;
        }


        if(!is_object($this->objectToPrint->$rootname)){
            $wrapper = new \stdClass();
            $wrapper->$rootname = $this->objectToPrint->$rootname;
            $this->objectToPrint->$rootname = $wrapper;
        }

        $this->printObject($this->rootname . " version=\"1.0\" timestamp=\"" . time() . "\"", $this->objectToPrint->$rootname);
        echo "</$this->rootname>";
    }

    private function printObject($name,$object,$nameobject=null){

        //check on first character
        if(preg_match("/^[0-9]+.*/", $name)){
            $name = NUMBER_TAG_PREFIX . $name; // add an i
        }        
        echo "<".$name;
        //If this is not an object, it must have been an empty result
        //thus, we'll be returning an empty tag
        if(is_object($object)){
            $hash = get_object_vars($object);
            $tag_close = FALSE;

            foreach($hash as $key => $value){
                if(is_object($value)){
                    if($tag_close == FALSE){
                        echo ">";
                    }

                    $tag_close = TRUE;
                    $this->printObject($key,$value);
                }elseif(is_array($value)){
                    if($tag_close == FALSE){
                        echo ">";
                    }
                    $tag_close = TRUE;
                    $this->printArray($key,$value);
                }else{

                    if($key == $name){
                        echo ">" . htmlspecialchars($value, ENT_QUOTES);
                        $tag_close = TRUE;
                    }else{
                        $key = htmlspecialchars(str_replace(" ","",$key));                        

                        $value = htmlspecialchars($value, ENT_QUOTES);                        

                        if($this->isNotAnAttribute($key)){
                            if(!$tag_close){
                                echo ">";
                                $tag_close = TRUE;
                            }

                            if(preg_match("/^[0-9]+.*/", $key)){
                               $key = NUMBER_TAG_PREFIX . $key; // add an i
                            }
                            echo "<".$key.">" . $value . "</$key>";
                        }else{
                            // To be discussed: strip the _ or not to strip the _
                            //$key = substr($key, 1);
                            echo " $key=" .'"' .$value.'"';
                        }
                    }
                }
            }

            if($tag_close == FALSE){
                echo ">";
            }

            if($name != $nameobject){
                $boom = explode(" ",$name);
                if(count($boom) == 1){                    
                    echo "</$name>";
                }
            }

        }
    }

    private function isNotAnAttribute($key){        
        return $key[0] != "_";
    }

    private function printArray($name,$array){
        //check on first character        
        if(preg_match("/^[0-9]+.*/", $name)){
            $name = NUMBER_TAG_PREFIX . $name;
        }
        $index = 0;

        if(empty($array)){            
            echo "<$name></$name>";
        }

        foreach($array as $key => $value){
            $nametag = $name;
            if(is_object($value)){
                $this->printObject($nametag,$value,$name);            
                echo "</$name>";
            }else if(is_array($value) && !$this->isHash($value)){            
                echo "<".$name. ">";
                $this->printArray($nametag,$value);            
                echo "</".$name.">";
            }else if(is_array($value) && $this->isHash($value)){                
                echo "<".$name. ">";
                $this->printArray($key,$value);            
                echo "</".$name.">";
            }else{
                $name = htmlspecialchars(str_replace(" ","",$name));                
                $value = htmlspecialchars($value);                
                $key = htmlspecialchars(str_replace(" ","",$key));                

                if($this->isHash($array)){                    
                    if(preg_match("/^[0-9]+.*/", $key)){
                        $key = NUMBER_TAG_PREFIX . $key; 
                    }
                    echo "<".$key . ">" . $value  . "</".$key.">";
                }else{
                    echo "<".$name. ">".$value."</".$name.">";
                }

            }
            $index++;
        }
    }

    // Check if we have an hash or a normal 'numeric' array ( php doesn't know the difference btw, it just doesn't care. )
    private function isHash($arr){
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function getDocumentation(){
        return "Prints plain old XML. Watch out for tags starting with an integer: an i will be added.";
    }

    public function printGraph() {
        /* Serializer instantiation */
        $ser = \ARC2::getRDFXMLSerializer();
        foreach ($this->objectToPrint as $class => $prop)
            $triples = $prop->getTriples();
        /* Serialize a triples array */
        echo $ser->getSerializedTriples($triples);
    }
}