<?php
namespace app\views;
use app\models\Button;
use \app\libraries\GradeableType;
use app\models\User;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\TaGradedGradeable;
use app\models\gradeable\GradeableList;
use app\libraries\FileUtils;

class NavigationView extends AbstractView {

    const gradeableSections = [
        GradeableList::FUTURE => [
            //What title is displayed to the user for each category
            "title" => "FUTURE",
            //Shown italicized after the title
            "subtitle" => "visible only to Instructors",
            //Element id of the header row (used primarily by e2e tests)
            "section_id" => "future",
            //What bootstrap button the student button will be. Information about bootstrap buttons can be found here:
            //https://www.w3schools.com/bootstrap/bootstrap_buttons.asp
            "button_type_submission" => "btn-default",
            //What bootstrap button the instructor/TA button will be
            "button_type_grading" => "btn-default",
            //The general text of the button under the category
            //It is general since the text could change depending if the user submitted something or not and other factors.
            "prefix" => "ALPHA SUBMIT"
        ],
        GradeableList::BETA => [
            "title" => "BETA",
            "subtitle" => "open for testing by TAs",
            "section_id" => "beta",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-default",
            "prefix" => "BETA SUBMIT"
        ],
        GradeableList::OPEN => [
            "title" => "OPEN",
            "subtitle" => "",
            "section_id" => "open",
            "button_type_submission" => "btn-primary",
            "button_type_grading" => "btn-default",
            "prefix" => "SUBMIT"
        ],
        GradeableList::CLOSED => [
            "title" => "PAST DUE",
            "subtitle" => "",
            "section_id" => "closed",
            "button_type_submission" => "btn-danger",
            "button_type_grading" => "btn-default",
            "prefix" => "LATE SUBMIT"
        ],
        GradeableList::GRADING => [
            "title" => "CLOSED",
            "subtitle" => "being graded by TA/Instructor",
            "section_id" => "items_being_graded",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-primary",
            "prefix" => "VIEW SUBMISSION"
        ],
        GradeableList::GRADED => [
            "title" => "GRADES AVAILABLE",
            "subtitle" => "",
            "section_id" => "graded",
            "button_type_submission" => 'btn-default',
            "button_type_grading" => 'btn-default',
            "prefix" => "VIEW GRADE"
        ]
    ];

    const DATE_FORMAT = "m/d/Y @ h:i A";

    public function showGradeables($sections_to_list, $graded_gradeables, array $submit_everyone) {
        // ======================================================================================
        // DISPLAY CUSTOM BANNER (previously used to display room seating assignments)
        // note: placement of this information this may eventually be re-designed
        // note: in the future this could be extended to take other options, but right now it's
        //       for displaying a link to provided materials
        // ======================================================================================
        $display_custom_message = $this->core->getConfig()->displayCustomMessage();
        $message_file_details = null;
        //Course settings have enabled displaying custom (banner) message
        if($display_custom_message) {
            $message_file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", "summary_html", $this->core->getUser()->getId() . ".json");
            $display_custom_message = is_file($message_file_path);
            //If statement seems redundant, but will help in case we ever decouple the is_file check from $display_custom_message
            if ($display_custom_message && is_file($message_file_path)) {
                $message_json = json_decode(file_get_contents($message_file_path));
                if(property_exists($message_json, 'special_message')){
                    $message_file_details = $message_json->special_message;

                    //If any fields are missing, treat this as though we just didn't have a message for this user.
                    if(!property_exists($message_file_details,'title') || !property_exists($message_file_details,'description') || !property_exists($message_file_details,'filename') ){
                        $display_custom_message = false;
                        $messsage_file_details = null;
                    }
                }
            }
        }


        // ======================================================================================
        // DISPLAY ROOM SEATING (used to display room seating assignments)
        // ======================================================================================
        $seating_only_for_instructor = $this->core->getConfig()->isSeatingOnlyForInstructor();
        if ($seating_only_for_instructor && !$this->core->getUser()->accessAdmin()) {
            $display_room_seating = false;
        }
        else {
            $display_room_seating = $this->core->getConfig()->displayRoomSeating();
        }
        $user_seating_details = null;
        $gradeable_title = null;
        $seating_config = null;
        // If the instructor has selected a gradeable for room seating
        if($display_room_seating) {
            $this->core->getOutput()->addRoomTemplatesTwigPath();
            // use the room seating gradeable id to find the title to display.
            $gradeable_id = $this->core->getConfig()->getRoomSeatingGradeableId();
            $gradeable_ids_and_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
            foreach($gradeable_ids_and_titles as $gradeable_id_and_title) {
                if($gradeable_id_and_title['g_id'] === $gradeable_id) {
                    $gradeable_title = $gradeable_id_and_title['g_title'];
                    break;
                }
            }

            $seating_user_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'seating', $gradeable_id, $this->core->getUser()->getId() . ".json");
            // if the instructor has generated a report for the student for this gradeable
            if(is_file($seating_user_path)) {
                $user_seating_details = json_decode(file_get_contents($seating_user_path));

                // if the user seating details have both a building and a room property
                if(property_exists($user_seating_details, 'building') && property_exists($user_seating_details, 'room')) {
                    $seating_config_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'uploads', 'seating',
                        $gradeable_id, $user_seating_details->building, $user_seating_details->room . '.json');
                    // if the report the instructor generated corresponds to a valid room config and a valid room template
                    if (is_file($seating_config_path) && is_file(FileUtils::joinPaths(dirname(dirname(__DIR__)), 'room_templates', $user_seating_details->building, $user_seating_details->room . '.twig'))) {
                        $seating_config = file_get_contents($seating_config_path);
                    }
                }
            }
            else {
                // mimic the result format of json_decode when there is no file to decode
                // and make each field the default value
                $user_seating_details = new \stdClass();
                $user_seating_details->building =
                $user_seating_details->zone     =
                $user_seating_details->row      =
                $user_seating_details->seat     = "SEE INSTRUCTOR";
            }
        }

        // ======================================================================================
        // INDEX OF ALL GRADEABLES
        // ======================================================================================

        $render_sections = [];
        $max_buttons = 0;
        foreach ($sections_to_list as $list_section => $gradeable_list) {
            /** @var Gradeable[] $gradeable_list */

            $render_gradeables = [];
            foreach ($gradeable_list as $gradeable_id => $gradeable) {
                /** @var Gradeable $gradeable */

                $graded_gradeable = $graded_gradeables[$gradeable->getId()] ?? null;
                $buttons = $this->getButtons($gradeable, $graded_gradeable, $list_section, $submit_everyone[$gradeable->getId()]);
                $render_gradeables[] = [
                    "id" => $gradeable->getId(),
                    "name" => $gradeable->getTitle(),
                    "url" => $gradeable->getInstructionsUrl(),
                    "edit_buttons" => $this->getAllEditButtons($gradeable),
                    "delete_buttons" => $this->getAllDeleteButtons($gradeable),
                    "buttons" => $buttons,
                    "has_build_error" => $gradeable->anyBuildErrors()
                ];

                if (count($buttons) > $max_buttons) {
                    $max_buttons = count($buttons);
                }
            }

            //Copy
            $render_section = self::gradeableSections[$list_section];
            $render_section["gradeables"] = $render_gradeables;

            $render_sections[] = $render_section;
        }

        $this->core->getOutput()->addInternalCss("navigation.css");

        return $this->core->getOutput()->renderTwigTemplate("Navigation.twig", [
            "course_name" => $this->core->getConfig()->getCourseName(),
            "course_id" => $this->core->getConfig()->getCourse(),
            "sections" => $render_sections,
            "max_buttons" => $max_buttons,
            "message_file_details" => $message_file_details,
            "display_custom_message" => $display_custom_message,
            "user_seating_details" => $user_seating_details,
            "display_room_seating" => $display_room_seating,
            "seating_only_for_instructor" => $this->core->getConfig()->isSeatingOnlyForInstructor(),
            "gradeable_title" => $gradeable_title,
            "seating_config" => $seating_config
        ]);
    }

    /**
     * Get the list of buttons to display to the user for a Gradeable
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable The graded gradeble instance, or null if no data yet
     * @param int $list_section
     * @param bool $submit_everyone If the user can submit for another user
     * @return array
     */
    private function getButtons(Gradeable $gradeable, $graded_gradeable, int $list_section, bool $submit_everyone): array {
        $buttons = [];
        $buttons[] = $this->hasTeamButton($gradeable) ? $this->getTeamButton($gradeable, $graded_gradeable) : null;
        $buttons[] = $this->hasSubmitButton($gradeable) ? $this->getSubmitButton($gradeable, $graded_gradeable, $list_section, $submit_everyone) : null;

        if ($this->hasGradeButton($gradeable)) {
            $buttons[] = $this->getGradeButton($gradeable, $list_section);
        }

        //Admin buttons
        if ($this->core->getUser()->accessAdmin()) {
            $buttons[] = $this->hasQuickLinkButton() ? $this->getQuickLinkButton($gradeable, $list_section) : null;
        }

        return $buttons;
    }

    /**
     * Get a list with the edit buttons (if applicable) to display to the user for a Gradeable
     * @param Gradeable $gradeable
     * @return array
     */
    private function getAllEditButtons(Gradeable $gradeable): array {
        $buttons = [];

        //Admin buttons
        if ($this->core->getUser()->accessAdmin()) {
            $buttons[] = $this->hasEditButton() ? $this->getEditButton($gradeable) : null;
        }

        return $buttons;
    }

    /**
     * Get a list with the edit buttons (if applicable) to display to the user for a Gradeable
     * @param Gradeable $gradeable
     * @return array
     */
    private function getAllDeleteButtons(Gradeable $gradeable): array {
        $buttons = [];

        //Admin buttons
        if ($this->core->getUser()->accessAdmin()) {
            $buttons[] = $gradeable->canDelete() ? $this->getDeleteButton($gradeable) : null;
        }

        return $buttons;
    }

    //Tests for if we have the various buttons

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasTeamButton(Gradeable $gradeable): bool {
        return $gradeable->isTeamAssignment();
    }

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasSubmitButton(Gradeable $gradeable): bool {
        return $gradeable->getType() === GradeableType::ELECTRONIC_FILE;
    }

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasGradeButton(Gradeable $gradeable): bool {
        // full access graders & instructors are allowed to view submissions of assignments with no manual grading
        $im_allowed_to_view_submissions = $this->core->getUser()->accessGrading() && !$gradeable->isTaGrading() && $this->core->getUser()->accessFullGrading();

        // limited access graders and full access graders can preview/view the grading interface only if they are allowed by the min grading group
        $im_a_grader = $this->core->getUser()->accessGrading() && $this->core->getUser()->getGroup() <= $gradeable->getMinGradingGroup();

        // students can only view the submissions & grading interface if its a peer grading assignment
        $im_a_peer_grader = $this->core->getUser()->getGroup() === User::GROUP_STUDENT && $gradeable->isPeerGrading();

        // TODO: look through this logic and put into new access system
        return $im_a_peer_grader || $im_a_grader || $im_allowed_to_view_submissions;
    }

    /**
     * @return bool
     */
    private function hasEditButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }


    /**
     * @return bool
     */
    private function hasQuickLinkButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @return Button|null
     */
    private function getTeamButton(Gradeable $gradeable, $graded_gradeable) {
        // Team management button, only visible on team assignments
        $date = $this->core->getDateTimeNow();
        $past_lock_date = $date < $gradeable->getTeamLockDate();

        if ($past_lock_date) {
            $team_display_date = "(teams lock {$gradeable->getTeamLockDate()->format(self::DATE_FORMAT)})";
        } else {
            $team_display_date = '';
        }

        if ($graded_gradeable === null || $graded_gradeable->getSubmitter()->getTeam() === null) {
            if ($past_lock_date) {
                $team_button_type = 'btn-primary';
            } else {
                $team_button_type = 'btn-danger';
            }
            $team_button_text = 'CREATE TEAM';
            $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable->getId());
            foreach ($teams as $t) {
                if ($t->sentInvite($this->core->getUser()->getId())) {
                    $team_button_text = 'CREATE/JOIN TEAM';
                    break;
                }
            }
        } else {
            if ($past_lock_date) {
                $team_button_type = 'btn-primary';
                $team_button_text = 'MANAGE TEAM';
            } else {
                $team_button_type = 'btn-default';
                $team_button_text = 'VIEW TEAM';
            }
        }

        $button = new Button($this->core, [
            "title" => $team_button_text,
            "subtitle" => $team_display_date,
            "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'team']),
            "class" => "btn {$team_button_type} btn-nav",
            "name" => "team-btn"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param int $list_section
     * @param bool $submit_everyone If the user can submit for another user
     * @return Button|null
     */
    private function getSubmitButton(Gradeable $gradeable, $graded_gradeable, int $list_section, bool $submit_everyone) {
        $class = self::gradeableSections[$list_section]["button_type_submission"];
        $title = self::gradeableSections[$list_section]["prefix"];
        $display_date = ($list_section == GradeableList::FUTURE || $list_section == GradeableList::BETA) ?
            "(opens " . $gradeable->getSubmissionOpenDate()->format(self::DATE_FORMAT) . ")" :
            "(due " . $gradeable->getSubmissionDueDate()->format(self::DATE_FORMAT) . ")";
        $points_percent = NAN;

        $href = $this->core->buildCourseUrl(['gradeable', $gradeable->getId()]);
        $progress = null;
        $disabled = false;

        //Button types that override any other buttons
        if (!$gradeable->hasAutogradingConfig()) {
            $button = new Button($this->core, [
                "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                "disabled" => true,
                "class" => "btn btn-default btn-nav"
            ]);

            return $button;
        }

        if($graded_gradeable !== null) {
            /** @var TaGradedGradeable $ta_graded_gradeable */
            $ta_graded_gradeable = $graded_gradeable->getTaGradedGradeable();
            /** @var AutoGradedGradeable $auto_graded_gradeable */
            $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();

            //calculate the point percentage
            if($auto_graded_gradeable !== null) {
                $points_percent = $auto_graded_gradeable->getNonHiddenPercent(true);
            }


            //If the button is autograded and has been submitted once, give a progress bar.
            if (!is_nan($points_percent) &&  $graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::CLOSED || $list_section == GradeableList::OPEN)) {
                $progress = $points_percent * 100;
            }

            // Not submitted or cancelled, after submission deadline
            if (!$graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                //You forgot to submit
                $class = "btn-danger";
            }

            // TA grading enabled, the gradeable is fully graded, and the user hasn't viewed it
            $grade_ready_for_view = $gradeable->isTaGrading() && $graded_gradeable->isTaGradingComplete() &&
                $list_section === GradeableList::GRADED;
            if ($gradeable->isTeamAssignment()) {
                if ($grade_ready_for_view &&
                    $this->core->getQueries()->getTeamViewedTime($graded_gradeable->getSubmitter()->getId(),$this->core->getUser()->getId()) === null) {
                    $class = "btn-success";
                }
            }
            else {
                if ($grade_ready_for_view && $ta_graded_gradeable->getUserViewedDate() === null) {
                    //Graded and you haven't seen it yet
                    $class = "btn-success";
                }
            }

            // Submitted, currently after grade released date
            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                $list_section == GradeableList::GRADED) {
                if ($gradeable->isTaGrading()) {
                    if (!$graded_gradeable->isTaGradingComplete()) {
                        // Incomplete TA grading
                        $class = "btn-default";
                    }
                } else {
                    // No TA grading
                    $class = "btn-default";
                }
            }

            // Due date passed with at least 50 percent points in autograding or gradable with no autograding points
            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                (
                    !$gradeable->getAutogradingConfig()->anyPoints() ||
                    $gradeable->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit() != 0 && $points_percent >= 0.5
                ) &&
                $list_section == GradeableList::CLOSED) {
                $class = "btn-default";
            }

            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                $display_date = "";
            }
            if (!$gradeable->hasDueDate()) {
                $display_date = "";
            }
            if (!$gradeable->isStudentSubmit() && $this->core->getUser()->accessFullGrading()) {
                // Student isn't submitting
                $title = "BULK UPLOAD";
                $class = "btn-primary";
                $display_date = "";
            } else if ($gradeable->isStudentSubmit() && !$gradeable->hasDueDate() && $list_section != GradeableList::OPEN) {
                $title = "SUBMIT";
                $class = "btn-default";
            } else if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && $list_section == GradeableList::OPEN) {
                //if the user submitted something on time
                $title = "RESUBMIT";
            } else if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && $list_section == GradeableList::CLOSED) {
                //if the user submitted something past time
                if ($gradeable->isLateSubmissionAllowed()) {
                    $title = "LATE RESUBMIT";
                } else {
                    $title = "VIEW SUBMISSION";
                    $class = 'btn-default';
                    $display_date = "";
                }
            } else if (!$graded_gradeable->getAutoGradedGradeable()->hasSubmission() && !$gradeable->isLateSubmissionAllowed() && $list_section == GradeableList::CLOSED) {
                $title = "NO SUBMISSION";
                $class = "btn-danger";
                $display_date = "";
            } else if (!$graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                //to change the text to overdue submission if nothing was submitted on time
                if ($gradeable->isStudentSubmit()) {
                    $title = "OVERDUE SUBMISSION";
                } else {
                    $title = "NO SUBMISSION";
                    $display_date = "";
                }
            } else if ($gradeable->isTaGrading() && !$graded_gradeable->isTaGradingComplete() && $list_section == GradeableList::GRADED) {
                //when there is no TA grade and due date passed
                $title = "TA GRADE NOT AVAILABLE";
            }
        } else {
            // This means either the user isn't on a team
            if ($gradeable->isTeamAssignment()) {
                // team assignment, no team
                if (!$submit_everyone) {
                    $title = "MUST BE ON A TEAM TO SUBMIT";
                    $disabled = true;
                }
                if ($list_section > GradeableList::OPEN) {
                    $class = "btn-danger";
                    if ($submit_everyone) {
                        // team assignment, no team
                        $title = "OVERDUE SUBMISSION";
                        $disabled = false;
                    }
                }
            }
        }

        $button = new Button($this->core, [
            "title" => $title,
            "subtitle" => $display_date,
            "href" => $href,
            "progress" => $progress,
            "disabled" => $disabled,
            "class" => "btn {$class} btn-nav btn-nav-submit",
            "name" => "submit-btn"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return Button|null
     */
    private function getGradeButton(Gradeable $gradeable, int $list_section) {
        //Location, location never changes
        if($this->core->getUser()->accessAdmin()){
            $view="all";
        }
        else{
            $view=null;
        }
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $href = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'status']);
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS || $gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $href = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading']) . '?view=' . $view;
        }
        else {
            //Unknown type of gradeable
            $href = "";
        }

        //Default values
        $class = self::gradeableSections[$list_section]["button_type_grading"];
        $date_text = null;
        $progress = null;

        //Button types that override any other buttons
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            if (!$gradeable->hasAutogradingConfig()) {
                $button = new Button($this->core, [
                    "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                    "disabled" => true,
                    "class" => "btn btn-default btn-nav"
                ]);

                return $button;
            }

            if ($gradeable->anyActiveRegradeRequests()) {
                //Open grade inquiries
                $button = new Button($this->core, [
                    "title" => "REGRADE",
                    "class" => "btn btn-danger btn-nav btn-nav-grade",
                    "href" => $href
                ]);

                return $button;
            }
        }

        if ($list_section === GradeableList::GRADING || $list_section === GradeableList::GRADED) {
            $date = $this->core->getDateTimeNow();
            $grades_due = $gradeable->getGradeDueDate();
            $grades_released = $gradeable->getGradeReleasedDate();
            if ($list_section === GradeableList::GRADING && $date < $grades_due ) {
                $title = 'GRADE';
                $date_text = '(grades due ' . $gradeable->getGradeDueDate()->format(self::DATE_FORMAT) . ')';
            } else if($list_section === GradeableList::GRADING && $date < $grades_released){
                $title = 'GRADE';
                $date_text = '(grades will be released ' . $grades_released->format(self::DATE_FORMAT) . ')';
            }
            else {
                $title = 'REGRADE';
            }

            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->isTaGrading()) {
                    $TA_percent = $gradeable->getGradingProgress($this->core->getUser());

                    if ($TA_percent === 1) {
                        //If they're done, change the text to REGRADE
                        $class = 'btn-default';
                        $title = 'REGRADE';
                    } else {
                        if (!is_nan($TA_percent) && $list_section === GradeableList::GRADED) {
                            //You forgot somebody
                            $class = 'btn-danger';
                            $title = 'GRADE';
                        } else if(!is_nan($TA_percent) && $list_section === GradeableList::GRADING && $grades_due < $date && $date < $grades_released){
                            $class = 'btn-danger';
                            $title = 'GRADE';
                        }
                    }

                    //Give the TAs a progress bar too
                    if (!is_nan($TA_percent)) {
                        $progress = $TA_percent * 100;
                    }
                } else {
                    $title = "VIEW SUBMISSIONS";
                }
            } else {
                //Labs & Tests don't have exciting buttons
                $class = 'btn-default';
            }
        } else {
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && !$gradeable->isTaGrading()) {
                $title = "VIEW SUBMISSIONS";
                $date_text = "(no manual grading)";
            } else {
                //Before grading has opened, only thing we can do is preview
                $title = 'PREVIEW GRADING';
                $date_text = '(grading starts ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT) . ")";
            }
        }

        $button = new Button($this->core, [
            "title" => $title,
            "subtitle" => $date_text,
            "href" => $href,
            "progress" => $progress,
            "class" => "btn btn-nav btn-nav-grade {$class}",
            "name" => "grade-btn"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @return Button|null
     */
    private function getEditButton(Gradeable $gradeable) {
        $button = new Button($this->core, [
            "title" => "Edit Gradeable Configuration",
            "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'update']),
            "class" => "fas fa-pencil-alt black-btn",
            "title_on_hover" => true,
            "aria_label" => "edit gradeable {$gradeable->getTitle()}"
        ]);
        return $button;
    }

        /**
     * @param Gradeable $gradeable
     * @return Button|null
     */
    private function getDeleteButton(Gradeable $gradeable) {
        $button = new Button($this->core, [
            "title" => "Delete Gradeable",
            "href" => "javascript:newDeleteGradeableForm('" .
                $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'delete'])
                . "', '{$gradeable->getTitle()}');",
            "class" => "fas fa-trash fa-fw black-btn",
            "title_on_hover" => true,
            "aria_label" => "Delete {$gradeable->getTitle()}"
        ]);
        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return Button|null
     */
    private function getQuickLinkButton(Gradeable $gradeable, int $list_section) {
        $button = null;
        if ($list_section === GradeableList::GRADING) {
            $button = new Button($this->core, [
                "subtitle" => "RELEASE GRADES NOW",
                "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                    . http_build_query(['action' => 'release_grades_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open",
                "name" => "quick-link-btn"
            ]);
        } else if ($list_section === GradeableList::FUTURE) {
            $button = new Button($this->core, [
                "subtitle" => "OPEN TO TAS NOW",
                "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                    . http_build_query(['action' => 'open_ta_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open",
                "name" => "quick-link-btn"
            ]);
        } else if ($list_section === GradeableList::BETA) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                $button = new Button($this->core, [
                    "subtitle" => "OPEN NOW",
                    "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                        . http_build_query(['action' => 'open_students_now']),
                    "class" => "btn btn-primary btn-nav btn-nav-open",
                    "name" => "quick-link-btn"
                ]);
            } else {
                $button = new Button($this->core, [
                    "subtitle" => "OPEN TO GRADING NOW",
                    "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                        . http_build_query(['action' => 'open_grading_now']),
                    "class" => "btn btn-primary btn-nav btn-nav-open",
                    "name" => "quick-link-btn"
                ]);
            }
        } else if ($list_section === GradeableList::CLOSED) {
            $button = new Button($this->core, [
                "subtitle" => "OPEN TO GRADING NOW",
                "href" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                    . http_build_query(['action' => 'open_grading_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open",
                "name" => "quick-link-btn"
            ]);
        } else if ($list_section === GradeableList::OPEN) {
            $url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'quick_link']) . '?'
                . http_build_query(['action' => 'close_submissions']);

            $button = new Button($this->core, [
                "subtitle" => "CLOSE SUBMISSIONS NOW",
                "onclick" => "displayCloseSubmissionsWarning(\"".$url."\",\"".$gradeable->getTitle()."\");",
                "class" => "btn btn-default btn-nav btn-nav-open",
                "name" => "quick-link-btn"
            ]);
        }

        if ($button !== null) {
            return $button;
        }

        return null;
    }

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate(
            "navigation/DeleteGradeableForm.twig",
            ['csrf_token' => $this->core->getCsrfToken()]
        );
    }

    public function closeSubmissionsWarning() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/CloseSubmissionsWarning.twig");
    }
}
