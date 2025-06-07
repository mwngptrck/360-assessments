<?php
require_once(plugin_dir_path(__FILE__) . '../libs/tcpdf/tcpdf.php');

class Assessment_360_PDF_Generator extends TCPDF {
    protected $custom_header_title = ''; // Changed from private $header_title

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);
        
        // Set document information
        $this->SetCreator('360 Assessment System');
        $this->SetAuthor('360 Assessment System');
        
        // Set default header/footer data
        $this->setHeaderFont(Array('helvetica', '', 10));
        $this->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $this->SetMargins(15, 25, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(TRUE, 25);
    }

    public function setHeaderTitle($title) {
        $this->custom_header_title = $title; // Use new property name
    }

    public function Header() {
        if ($this->page == 1) {
            return; // No header on first page
        }
        
        $this->SetY(5);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 10, $this->custom_header_title, 0, false, 'L', 0); // Use new property name
        $this->Line(15, 18, 195, 18);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0);
    }

    public function generateAssessmentReport($assessment_id, $user_id) {
        try {
            // Get assessment and user data
            $assessment = Assessment_360_Assessment::get_instance();
            $user_manager = Assessment_360_User_Manager::get_instance();

            $assessment_data = $assessment->get_assessment($assessment_id);
            $user_data = $user_manager->get_user($user_id);

            if (!$assessment_data || !$user_data) {
                throw new Exception('Assessment or user not found');
            }

            // Set document information
            $this->SetTitle("360° Assessment Report - {$user_data->first_name} {$user_data->last_name}");
            $this->setHeaderTitle("360° Assessment - {$user_data->first_name} {$user_data->last_name}");
            $this->SetSubject('360° Assessment Report');
            $this->SetKeywords('360, Assessment, Feedback');

            // Add cover page
            $this->AddPage();
            $this->addCoverPage($assessment_data, $user_data);

            // Add introduction page
            $this->AddPage();
            $this->addIntroductionPage($assessment_data, $user_data);

            // Get assessment results
            $results = $assessment->get_user_assessment_results($assessment_id, $user_id);
            if (empty($results)) {
                throw new Exception('No results found for this assessment');
            }

            // Determine if this is user's first assessment
            $is_first_assessment = !$assessment->has_previous_assessments($user_id);

            // Add results pages
            if ($is_first_assessment) {
                $this->addFirstAssessmentResults($results);
            } else {
                // Get previous assessments for comparison
                $previous_assessments = $assessment->get_user_previous_assessments($user_id, $assessment_id);
                $this->addComparativeResults($results, $previous_assessments);
            }

            // Add summary page
            $this->AddPage();
            $this->addSummaryPage($assessment_data, $user_data, $results);

            // Add comments section
            $this->AddPage();
            $comments = $assessment->get_assessment_comments($assessment_id, $user_id);
            $this->addCommentsSection($comments);

            // Add action plan section
            $this->AddPage();
            $this->addActionPlanSection();

            // Add final page with confidentiality notice
            $this->AddPage();
            $this->addConfidentialityNotice();

            return true;

        } catch (Exception $e) {
            error_log('PDF Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    private function addCoverPage($assessment, $user) {
        // Add logo if exists
        $logo_url = get_option('assessment_360_organization_logo');
        if ($logo_url) {
            $this->Image($logo_url, 15, 15, 50);
        }

        // Title
        $this->SetY(60);
        $this->SetFont('helvetica', 'B', 24);
        $this->Cell(0, 15, '360° Assessment Report', 0, 1, 'C');

        // User info
        $this->SetY(100);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(40, 10, 'CANDIDATE:', 0, 0);
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 10, $user->first_name . ' ' . $user->last_name, 0, 1);

        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(40, 10, 'POSITION:', 0, 0);
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 10, $user->position_name ?? '—', 0, 1);

        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(40, 10, 'DEPARTMENT:', 0, 0);
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 10, $user->group_name ?? '—', 0, 1);

        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(40, 10, 'DATE:', 0, 0);
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 10, date('F j, Y'), 0, 1);

        // Legend
        $this->SetY(150);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Rating Scale:', 0, 1);

        $this->SetFont('helvetica', '', 12);
        $ratings = [
            '5' => 'Excellent',
            '4' => 'Very Good',
            '3' => 'Satisfactory',
            '2' => 'Concern',
            '1' => 'Unsatisfactory'
        ];

        foreach ($ratings as $score => $label) {
            $this->Cell(0, 8, $score . ' - ' . $label, 0, 1);
        }

        // Confidentiality
        $this->SetY(-50);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 10, 'CONFIDENTIALITY: HIGH', 0, 1, 'C');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'This document contains confidential information. Do not distribute without authorization.', 0, 1, 'C');
    }

    private function addIntroductionPage($assessment, $user) {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Introduction', 0, 1);

        $this->SetFont('helvetica', '', 11);
        $intro_text = "This feedback report contains confidential information about {$user->first_name} {$user->last_name} " .
                      "and should only be shared with authorized individuals.\n\n";

        $intro_text .= "The report has been designed to provide feedback on performance, leadership, and management behavior " .
                      "and its impact on colleagues and stakeholders.\n\n";

        $intro_text .= "This assessment will help identify strengths and areas for improvement, enabling the development " .
                      "of an appropriate action plan to enhance leadership and management capabilities.\n\n";

        $intro_text .= "The feedback has been collected anonymously from various stakeholders, including self-assessment. " .
                      "Ratings are provided on a scale of 1 to 5, where:\n\n";

        $this->MultiCell(0, 10, $intro_text, 0, 'L');

        // Rating scale explanation
        $ratings = [
            '5 - Excellent' => 'Consistently exceeds expectations',
            '4 - Very Good' => 'Often exceeds expectations',
            '3 - Satisfactory' => 'Meets expectations',
            '2 - Concern' => 'Below expectations',
            '1 - Unsatisfactory' => 'Significantly below expectations'
        ];

        foreach ($ratings as $rating => $description) {
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(40, 8, $rating, 0, 0);
            $this->SetFont('helvetica', '', 11);
            $this->Cell(0, 8, $description, 0, 1);
        }
    }

    private function addConfidentialityNotice() {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 15, 'Confidentiality Notice', 0, 1, 'C');

        $this->SetFont('helvetica', '', 11);
        $notice = "This document contains confidential information and is intended solely for the individual named herein " .
                  "and their authorized reviewers. Any unauthorized disclosure, copying, or distribution of this document " .
                  "is strictly prohibited.\n\n";

        $notice .= "The information within this report should be used constructively to support professional development " .
                   "and enhance performance. The feedback provided represents individual perspectives and should be " .
                   "considered as part of a broader development context.\n\n";

        $notice .= "For any questions about this report or its appropriate use, please contact the HR department or your " .
                   "immediate supervisor.";

        $this->MultiCell(0, 10, $notice, 0, 'L');

        // Add footer with date and document ID
        $this->SetY(-40);
        $this->SetFont('helvetica', 'I', 10);
        $this->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');
        $this->Cell(0, 10, 'Document ID: ' . uniqid('360-'), 0, 1, 'C');
    }
    
    private function addSummaryPage($assessment, $user, $results) {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Assessment Summary', 0, 1);

        // Overall Statistics
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Overall Statistics', 0, 1);

        // Calculate overall statistics
        $total_ratings = 0;
        $total_count = 0;
        $ratings_by_group = [
            'Self' => [],
            'Peers' => [],
            'Department' => [],
            'Others' => []
        ];

        foreach ($results as $topic) {
            if (isset($topic['sections'])) {
                foreach ($topic['sections'] as $section) {
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $question) {
                            if (isset($question['ratings'])) {
                                foreach ($question['ratings'] as $rating) {
                                    $total_ratings += $rating;
                                    $total_count++;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Display overall average
        $overall_average = $total_count > 0 ? round($total_ratings / $total_count, 2) : 0;

        $this->SetFont('helvetica', '', 11);

        // Create statistics table
        $this->SetFillColor(248, 249, 250);
        $this->Cell(100, 10, 'Overall Average Rating:', 1, 0, 'L', true);
        $this->Cell(30, 10, number_format($overall_average, 2), 1, 1, 'C', true);

        $this->Cell(100, 10, 'Total Questions:', 1, 0, 'L');
        $this->Cell(30, 10, count($results), 1, 1, 'C');

        $this->Cell(100, 10, 'Total Responses:', 1, 0, 'L');
        $this->Cell(30, 10, $total_count, 1, 1, 'C');

        // Rating Distribution
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Rating Distribution', 0, 1);

        // Create rating scale table
        $this->SetFont('helvetica', '', 11);
        $ratings = [
            5 => 'Excellent',
            4 => 'Very Good',
            3 => 'Satisfactory',
            2 => 'Concern',
            1 => 'Unsatisfactory'
        ];

        $rating_counts = array_fill(1, 5, 0);
        foreach ($results as $topic) {
            if (isset($topic['sections'])) {
                foreach ($topic['sections'] as $section) {
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $question) {
                            if (isset($question['ratings'])) {
                                foreach ($question['ratings'] as $rating) {
                                    if (isset($rating_counts[round($rating)])) {
                                        $rating_counts[round($rating)]++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Display rating distribution
        $this->SetFillColor(248, 249, 250);
        $this->Cell(30, 10, 'Rating', 1, 0, 'C', true);
        $this->Cell(70, 10, 'Description', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Count', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Percentage', 1, 1, 'C', true);

        foreach ($ratings as $rating => $description) {
            $count = $rating_counts[$rating];
            $percentage = $total_count > 0 ? round(($count / $total_count) * 100, 1) : 0;

            $this->Cell(30, 10, $rating, 1, 0, 'C');
            $this->Cell(70, 10, $description, 1, 0, 'L');
            $this->Cell(30, 10, $count, 1, 0, 'C');
            $this->Cell(30, 10, $percentage . '%', 1, 1, 'C');
        }

        // Key Observations
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Key Observations', 0, 1);

        $this->SetFont('helvetica', '', 11);

        // Strengths (ratings >= 4)
        $this->Cell(0, 10, 'Areas of Strength:', 0, 1);
        $strengths_found = false;
        foreach ($results as $topic) {
            if (isset($topic['sections'])) {
                foreach ($topic['sections'] as $section) {
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $question) {
                            if (isset($question['average_rating']) && $question['average_rating'] >= 4) {
                                $strengths_found = true;
                                $this->MultiCell(0, 10, '• ' . $question['text'] . ' (' . number_format($question['average_rating'], 1) . ')', 0, 'L');
                            }
                        }
                    }
                }
            }
        }
        if (!$strengths_found) {
            $this->MultiCell(0, 10, 'No significant strengths identified (ratings >= 4.0)', 0, 'L');
        }

        // Areas for Improvement (ratings <= 3)
        $this->Ln(5);
        $this->Cell(0, 10, 'Areas for Improvement:', 0, 1);
        $improvements_found = false;
        foreach ($results as $topic) {
            if (isset($topic['sections'])) {
                foreach ($topic['sections'] as $section) {
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $question) {
                            if (isset($question['average_rating']) && $question['average_rating'] <= 3) {
                                $improvements_found = true;
                                $this->MultiCell(0, 10, '• ' . $question['text'] . ' (' . number_format($question['average_rating'], 1) . ')', 0, 'L');
                            }
                        }
                    }
                }
            }
        }
        if (!$improvements_found) {
            $this->MultiCell(0, 10, 'No significant areas for improvement identified (ratings <= 3.0)', 0, 'L');
        }
    }

    private function addFirstAssessmentResults($results) {
        foreach ($results as $topic_name => $topic) {
            $this->AddPage();
            
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, $topic_name, 0, 1);
            
            foreach ($topic['sections'] as $section_name => $section) {
                $this->SetFont('helvetica', 'B', 14);
                $this->Cell(0, 10, $section_name, 0, 1);
                
                // Create results table
                $this->SetFont('helvetica', '', 10);
                
                // Table header
                $this->SetFillColor(240, 240, 240);
                $this->Cell(80, 8, 'Question', 1, 0, 'L', true);
                $this->Cell(30, 8, 'Average', 1, 0, 'C', true);
                $this->Cell(30, 8, 'Assessors', 1, 0, 'C', true);
                $this->Cell(0, 8, 'Comments', 1, 1, 'L', true);
                
                foreach ($section['questions'] as $question) {
                    $this->MultiCell(80, 8, $question['text'], 1, 'L');
                    $x = $this->GetX();
                    $y = $this->GetY();
                    $this->SetXY($x + 80, $y - 8);
                    $this->Cell(30, 8, number_format($question['average_rating'], 1), 1, 0, 'C');
                    $this->Cell(30, 8, $question['total_assessors'], 1, 0, 'C');
                    
                    // Comments
                    $comments = '';
                    if (!empty($question['comments'])) {
                        foreach ($question['comments'] as $comment) {
                            $comments .= "• {$comment['comment']}\n";
                        }
                    }
                    $this->MultiCell(0, 8, $comments, 1, 'L');
                }
                
                $this->Ln(5);
            }
        }
    }

    private function addComparativeResults($results) {
        // For each topic
        foreach ($results['topics'] as $topic_name => $topic) {
            $this->AddPage();
            
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, $topic_name, 0, 1);
            
            foreach ($topic['sections'] as $section_name => $section) {
                $this->SetFont('helvetica', 'B', 14);
                $this->Cell(0, 10, $section_name, 0, 1);
                
                foreach ($section['questions'] as $question) {
                    $this->SetFont('helvetica', '', 12);
                    $this->MultiCell(0, 8, $question['text'], 0, 'L');
                    
                    // Create bar chart image using Chart.js
                    $chart_data = [
                        'labels' => array_map(function($a) {
                            return date('M Y', strtotime($a->created_at));
                        }, $results['assessments']),
                        'datasets' => [
                            [
                                'label' => 'Self',
                                'data' => array_map(function($a) use ($question) {
                                    return $question['ratings'][$a->id]['Self']['average'] ?? null;
                                }, $results['assessments'])
                            ],
                            [
                                'label' => 'Peers',
                                'data' => array_map(function($a) use ($question) {
                                    return $question['ratings'][$a->id]['Peers']['average'] ?? null;
                                }, $results['assessments'])
                            ],
                            [
                                'label' => 'Department',
                                'data' => array_map(function($a) use ($question) {
                                    return $question['ratings'][$a->id]['Department']['average'] ?? null;
                                }, $results['assessments'])
                            ],
                            [
                                'label' => 'Others',
                                'data' => array_map(function($a) use ($question) {
                                    return $question['ratings'][$a->id]['Others']['average'] ?? null;
                                }, $results['assessments'])
                            ]
                        ]
                    ];
                    
                    // Generate chart image using Chart.js API or similar service
                    // Add chart image to PDF
                    // $this->Image($chart_image_url, 15, $this->GetY(), 180);
                    
                    $this->Ln(5);
                }
            }
        }
    }

    private function addCommentsSection($comments) {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Feedback Comments', 0, 1);

        $this->SetFont('helvetica', '', 11);
        $this->MultiCell(0, 10, 'This section contains anonymous feedback and comments from assessment participants.', 0, 'L');

        if (empty($comments)) {
            $this->SetFont('helvetica', 'I', 11);
            $this->Cell(0, 10, 'No comments provided.', 0, 1);
            return;
        }

        // Group comments by topic and section
        $grouped_comments = [];
        foreach ($comments as $comment) {
            $topic = $comment->topic_name ?? 'General';
            $section = $comment->section_name ?? 'General';

            if (!isset($grouped_comments[$topic])) {
                $grouped_comments[$topic] = [];
            }
            if (!isset($grouped_comments[$topic][$section])) {
                $grouped_comments[$topic][$section] = [];
            }

            $grouped_comments[$topic][$section][] = $comment;
        }

        // Display comments
        foreach ($grouped_comments as $topic => $sections) {
            $this->Ln(5);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, $topic, 0, 1);

            foreach ($sections as $section => $section_comments) {
                $this->SetFont('helvetica', 'B', 12);
                $this->Cell(0, 10, $section, 0, 1);

                foreach ($section_comments as $comment) {
                    $this->SetFont('helvetica', 'B', 11);
                    $this->MultiCell(0, 10, 'Question: ' . $comment->question_text, 0, 'L');

                    $this->SetFont('helvetica', 'I', 10);
                    $this->Cell(30, 10, 'From:', 0, 0);
                    $this->Cell(0, 10, $comment->assessor_group, 0, 1);

                    $this->SetFont('helvetica', '', 11);
                    $this->MultiCell(0, 10, 'Comment: ' . $comment->comment, 0, 'L');

                    $this->Ln(5);
                }
            }
        }

        // Add note about anonymity
        $this->Ln(10);
        $this->SetFont('helvetica', 'I', 10);
        $this->MultiCell(0, 10, 'Note: Comments are provided anonymously to encourage honest feedback. ' .
                                'They are grouped by assessor type to maintain context while preserving confidentiality.', 
                                0, 'L');
    }

    private function addActionPlanSection() {
        $this->AddPage();
        
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, '360° Balance Sheet & Action Plan', 0, 1);
        
        // Assets section
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Assets ("At my best")', 0, 1);
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Essence:', 0, 1);
        $this->Cell(0, 30, '', 1, 1); // Empty box for writing
        
        // Liabilities section
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Liabilities ("At my worst")', 0, 1);
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Essence:', 0, 1);
        $this->Cell(0, 30, '', 1, 1); // Empty box for writing
        
        // Action Plan
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Action Plan', 0, 1);
        
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'What are you going to do and by when?', 0, 1);
        $this->Cell(0, 50, '', 1, 1); // Empty box for writing
        
        $this->Cell(0, 10, 'Support and Target Date:', 0, 1);
        $this->Cell(0, 30, '', 1, 1); // Empty box for writing
        
        $this->Cell(0, 10, 'How will I know I am making Progress?', 0, 1);
        $this->Cell(0, 30, '', 1, 1); // Empty box for writing
    }
}