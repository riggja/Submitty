<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DiffViewer;
use app\libraries\Utils;

/**
 * Class GradeableAutocheck
 *
 * Contains information pertaining to the autocheck element that's contained within a
 * GradeableTestcase. There is 0+ autochecks per GradeableTestcase.
 *
 * @method string getIndex()
 * @method DiffViewer getDiffViewer()
 * @method string getDescription()
 * @method array[] getMessages()
 * @method boolean getPublic()
 */
class GradeableAutocheck extends AbstractModel {
    
    /** @property @var string */
    protected $index;
    
    /** @var DiffViewer DiffViewer instance to hold the student, instructor, and differences */
    protected $diff_viewer;
    
    /** @property @var string Description to show for displaying the diff */
    protected $description = "";
    
    /** @property @var array[] Message to show underneath the description for a diff */
    protected $messages = array();

    /** @property @var boolean If this check's file is in results_public */
    protected $public;

    /** @property @var boolean If this check's file should be displayed as a sequence diagram */
    protected $display_as_sequence_diagram;

    /**
     * GradeableAutocheck constructor.
     *
     * @param Core $core
     * @param $details
     * @param $course_path
     * @param $results_path
     * @param $results_public_path
     * @param $idx
     */
    public function __construct(Core $core, $details, $course_path, $results_path, $results_public_path, $idx) {
        parent::__construct($core);
        $this->index = $idx;

        if (isset($details['description'])) {
            $this->description = Utils::prepareHtmlString($details['description']);
        }
        
        if (isset($details['messages'])) {
            foreach ($details['messages'] as $message) {
                $this->messages[] = array('message' => Utils::prepareHtmlString($message['message']),
                                            'type' => Utils::prepareHtmlString($message['type']));
            }
        }
        
        if(isset($details["display_as_sequence_diagram"])){
            $this->display_as_sequence_diagram = $details["display_as_sequence_diagram"];
        }else{
            $this->display_as_sequence_diagram = false;
        }

        $actual_file = $expected_file = $difference_file = $image_difference ="";

        if(isset($details["actual_file"])) {
            $this->public = (isset($details["results_public"]) && $details["results_public"]);
            $path = ($this->public ? $results_public_path : $results_path) . "/details/" . $details["actual_file"];

            if (file_exists($path)) {
                $actual_file = $path;
            }
        }
    
        
    
        if(isset($details["expected_file"])) {
            if(substr($details["expected_file"],0,11) == "test_output"){
                if(file_exists($course_path . "/" . $details["expected_file"])){ 
                    $expected_file = $course_path . "/" . $details["expected_file"];
                } else {
                    $this->core->addErrorMessage("Expected file not found.");
                }
            } else if(substr($details["expected_file"],0,13) == "random_output"){
                if(file_exists($results_path . "/" . $details["expected_file"])){ 
                    $expected_file = $results_path. "/" . $details["expected_file"];
                } else {
                    $this->core->addErrorMessage("Expected file not found.");
                }
            // Try to find the file in the details directory. Do not print an error,
            // as the file is likely student generated.
            } else {
                if(file_exists($results_path . "/details/" . $details["expected_file"])){
                    $expected_file = $results_path . "/details/" . $details["expected_file"];
                }
            }
        }
        
        if(isset($details["difference_file"]) && file_exists($results_path . "/details/" . $details["difference_file"])) {
            $difference_file = $results_path . "/details/" . $details["difference_file"];
        }

        if(isset($details["image_difference_file"]) &&
            file_exists($results_path . "/details/" . $details["image_difference_file"])) {
            $this->index = $idx;
            $image_difference = $results_path . "/details/" . $details["image_difference_file"];
        }

        $this->diff_viewer = new DiffViewer($actual_file, $expected_file, $difference_file, $image_difference, $this->index);
    }
}
