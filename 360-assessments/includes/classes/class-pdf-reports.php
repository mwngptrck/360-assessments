<?php
require_once(plugin_dir_path(__FILE__) . '../libs/tcpdf/tcpdf.php');

class Assessment_360_PDF_Report extends TCPDF {
    private $report_type;
    private $custom_header_title;
    private $org_name;
    private $org_logo;

    public function __construct($report_type = 'detailed') {
        // Clear any existing output
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $this->report_type = $report_type;
        $this->org_name = get_option('assessment_360_organization_name', '');
        $this->org_logo = get_option('assessment_360_organization_logo', '');
        
        // Set document information
        $this->SetCreator('360° Assessment System');
        $this->SetAuthor($this->org_name);
        
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

    public function Header() {
        if ($this->page == 1) {
            return; // No header on first page
        }
        
        $this->SetY(5);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 10, $this->custom_header_title, 0, false, 'L', 0);
        $this->Line(15, 18, 195, 18);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0);
    }

    public function generateDetailedReport($assessment_id, $user_id) {
        // Get data
        $assessment = Assessment_360_Assessment::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        $assessment_data = $assessment->get_assessment($assessment_id);
        $user_data = $user_manager->get_user($user_id);
        $results = $assessment->get_user_assessment_results($assessment_id, $user_id);

        // Set document title
        $this->SetTitle("360° Detailed Assessment Report - {$user_data->first_name} {$user_data->last_name}");
        $this->custom_header_title = "360° Assessment - {$user_data->first_name} {$user_data->last_name}";

        // Add Cover Page
        $this->addCoverPage($assessment_data, $user_data);
        
        // Add Introduction
        $this->addIntroductionPage();
        
        // Add Summary
        $this->addSummaryPage($results);
        
        // Add Detailed Results
        $this->addDetailedResults($results);
        
        // Add Comments
        $this->addCommentsSection($results);
        
        // Add Action Plan
        $this->addActionPlanSection();
    }

    public function generateSummaryReport($assessment_id, $user_id) {
        // Similar to detailed but with less detail
        $assessment = Assessment_360_Assessment::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        $assessment_data = $assessment->get_assessment($assessment_id);
        $user_data = $user_manager->get_user($user_id);
        $results = $assessment->get_user_assessment_results($assessment_id, $user_id);

        $this->SetTitle("360° Summary Report - {$user_data->first_name} {$user_data->last_name}");
        $this->custom_header_title = "360° Assessment Summary - {$user_data->first_name} {$user_data->last_name}";

        // Add Cover
        $this->addCoverPage($assessment_data, $user_data);
        
        // Add Summary
        $this->addSummaryPage($results);
        
        // Add Key Points
        $this->addKeyPointsSection($results);
    }

    public function generateComparativeReport($assessment_id, $user_id) {
        $assessment = Assessment_360_Assessment::get_instance();
        $user_manager = Assessment_360_User_Manager::get_instance();
        
        $assessment_data = $assessment->get_assessment($assessment_id);
        $user_data = $user_manager->get_user($user_id);
        $current_results = $assessment->get_user_assessment_results($assessment_id, $user_id);
        $previous_results = $assessment->get_user_previous_results($user_id);

        $this->SetTitle("360° Comparative Report - {$user_data->first_name} {$user_data->last_name}");
        $this->custom_header_title = "360° Assessment Comparison - {$user_data->first_name} {$user_data->last_name}";

        // Add Cover
        $this->addCoverPage($assessment_data, $user_data);
        
        // Add Comparison
        $this->addComparisonSection($current_results, $previous_results);
        
        // Add Trends
        $this->addTrendsSection($current_results, $previous_results);
    }

    private function addCoverPage($assessment, $user) {
        $this->AddPage();
        
        // Add logo if exists
        if ($this->org_logo) {
            $this->Image($this->org_logo, 15, 15, 50);
        }

        // Title
        $this->SetY(60);
        $this->SetFont('helvetica', 'B', 24);
        $this->Cell(0, 15, '360° Assessment Report', 0, 1, 'C');
        
        // Report type subtitle
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, ucfirst($this->report_type) . ' Report', 0, 1, 'C');

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

        // Add rating scale
        $this->SetY(160);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Rating Scale:', 0, 1);
        
        $ratings = [
            5 => 'Exceptional - Consistently exceeds all expectations',
            4 => 'Above Average - Frequently exceeds expectations',
            3 => 'Satisfactory - Meets job requirements',
            2 => 'Needs Improvement - Sometimes falls short of requirements',
            1 => 'Unsatisfactory - Consistently falls short of requirements'
        ];

        foreach ($ratings as $score => $description) {
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(15, 8, $score, 0, 0);
            $this->SetFont('helvetica', '', 11);
            $this->Cell(0, 8, $description, 0, 1);
        }

        // Confidentiality notice
        $this->SetY(-50);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 10, 'CONFIDENTIALITY: HIGH', 0, 1, 'C');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'This document contains confidential information and should only be shared with authorized individuals.', 0, 1, 'C');
    }

    private function addIntroductionPage() {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Introduction', 0, 1);
        
        $this->SetFont('helvetica', '', 11);
        $intro_text = "This 360° assessment report provides comprehensive feedback on performance, leadership, and professional behaviors. " .
                     "The feedback has been gathered anonymously from various stakeholders including supervisors, peers, and team members.\n\n" .
                     "The report is designed to:\n" .
                     "• Identify strengths and areas for development\n" .
                     "• Provide objective feedback from multiple perspectives\n" .
                     "• Guide professional development and action planning\n" .
                     "• Track progress over time\n\n" .
                     "Please review this report thoroughly and use it as a tool for personal and professional development.";
        
        $this->MultiCell(0, 10, $intro_text, 0, 'L');
    }

    private function addSummaryPage($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Assessment Summary', 0, 1);

        // Calculate overall statistics
        $total_ratings = 0;
        $total_count = 0;
        $ratings_by_category = [];

        foreach ($results as $topic) {
            foreach ($topic['sections'] as $section) {
                foreach ($section['questions'] as $question) {
                    if (isset($question['average_rating'])) {
                        $total_ratings += $question['average_rating'];
                        $total_count++;
                    }
                }
            }
        }

        $overall_average = $total_count > 0 ? round($total_ratings / $total_count, 2) : 0;

        // Display overall score
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Overall Score: ' . number_format($overall_average, 2), 0, 1);

        // Add summary table
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(80, 10, 'Category', 1, 0, 'C');
        $this->Cell(30, 10, 'Average', 1, 0, 'C');
        $this->Cell(30, 10, 'Responses', 1, 1, 'C');

        $this->SetFont('helvetica', '', 10);
        foreach ($results as $topic_name => $topic) {
            $topic_total = 0;
            $topic_count = 0;

            foreach ($topic['sections'] as $section) {
                foreach ($section['questions'] as $question) {
                    if (isset($question['average_rating'])) {
                        $topic_total += $question['average_rating'];
                        $topic_count++;
                    }
                }
            }

            $topic_average = $topic_count > 0 ? round($topic_total / $topic_count, 2) : 0;

            $this->Cell(80, 8, $topic_name, 1, 0);
            $this->Cell(30, 8, number_format($topic_average, 2), 1, 0, 'C');
            $this->Cell(30, 8, $topic_count, 1, 1, 'C');
        }
    }

    private function addDetailedResults($results) {
        foreach ($results as $topic_name => $topic) {
            $this->AddPage();
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, $topic_name, 0, 1);

            foreach ($topic['sections'] as $section_name => $section) {
                $this->SetFont('helvetica', 'B', 14);
                $this->Cell(0, 10, $section_name, 0, 1);

                // Create results table
                $this->SetFont('helvetica', 'B', 10);
                $this->Cell(100, 8, 'Question', 1, 0);
                $this->Cell(30, 8, 'Rating', 1, 0, 'C');
                $this->Cell(30, 8, 'Responses', 1, 1, 'C');

                $this->SetFont('helvetica', '', 10);
                foreach ($section['questions'] as $question) {
                    $this->MultiCell(100, 8, $question['text'], 1, 'L');
                    $y = $this->GetY();
                    $this->SetXY($this->GetX() + 100, $y - 8);
                    $this->Cell(30, 8, number_format($question['average_rating'], 2), 1, 0, 'C');
                    $this->Cell(30, 8, $question['total_assessors'], 1, 1, 'C');
                }

                $this->Ln(5);
            }
        }
    }

    private function addCommentsSection($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Feedback Comments', 0, 1);

        foreach ($results as $topic_name => $topic) {
            foreach ($topic['sections'] as $section_name => $section) {
                foreach ($section['questions'] as $question) {
                    if (!empty($question['comments'])) {
                        $this->SetFont('helvetica', 'B', 11);
                        $this->Cell(0, 10, $question['text'], 0, 1);

                        $this->SetFont('helvetica', '', 10);
                        foreach ($question['comments'] as $comment) {
                            $this->MultiCell(0, 8, '• ' . $comment['comment'], 0, 'L');
                        }
                        $this->Ln(5);
                    }
                }
            }
        }
    }

    private function addActionPlanSection() {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Development Action Plan', 0, 1);

        // Strengths section
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Key Strengths to Leverage:', 0, 1);
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 10, '1. ________________________________________________', 0, 1);
        $this->Cell(0, 10, '2. ________________________________________________', 0, 1);
        $this->Cell(0, 10, '3. ________________________________________________', 0, 1);

        $this->Ln(10);

        // Development areas
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Areas for Development:', 0, 1);
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 10, '1. ________________________________________________', 0, 1);
        $this->Cell(0, 10, '2. ________________________________________________', 0, 1);
        $this->Cell(0, 10, '3. ________________________________________________', 0, 1);

        $this->Ln(10);

        // Action steps
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Action Steps:', 0, 1);
        
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(100, 10, 'Development Goal', 1, 0);
        $this->Cell(50, 10, 'Timeline', 1, 0);
        $this->Cell(40, 10, 'Support Needed', 1, 1);

        $this->SetFont('helvetica', '', 11);
        for ($i = 1; $i <= 3; $i++) {
            $this->Cell(100, 15, '', 1, 0);
            $this->Cell(50, 15, '', 1, 0);
            $this->Cell(40, 15, '', 1, 1);
        }
    }
    
    private function addKeyPointsSection($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Key Points Summary', 0, 1);

        // Calculate overall statistics
        $strengths = [];
        $improvements = [];
        $overall_rating = 0;
        $total_questions = 0;

        foreach ($results as $topic_name => $topic) {
            foreach ($topic['sections'] as $section_name => $section) {
                foreach ($section['questions'] as $question) {
                    $overall_rating += $question['average_rating'];
                    $total_questions++;

                    if ($question['average_rating'] >= 4) {
                        $strengths[] = [
                            'text' => $question['text'],
                            'rating' => $question['average_rating']
                        ];
                    } elseif ($question['average_rating'] <= 3) {
                        $improvements[] = [
                            'text' => $question['text'],
                            'rating' => $question['average_rating']
                        ];
                    }
                }
            }
        }

        $overall_average = $total_questions > 0 ? round($overall_rating / $total_questions, 2) : 0;

        // Overall Rating
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Overall Rating: ' . number_format($overall_average, 2), 0, 1);
        $this->Ln(5);

        // Strengths
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Key Strengths:', 0, 1);
        $this->SetFont('helvetica', '', 11);

        usort($strengths, function($a, $b) {
            return $b['rating'] <=> $a['rating'];
        });

        foreach (array_slice($strengths, 0, 5) as $strength) {
            $this->MultiCell(0, 8, '• ' . $strength['text'] . ' (' . number_format($strength['rating'], 1) . ')', 0, 'L');
        }
        $this->Ln(5);

        // Areas for Improvement
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Areas for Improvement:', 0, 1);
        $this->SetFont('helvetica', '', 11);

        usort($improvements, function($a, $b) {
            return $a['rating'] <=> $b['rating'];
        });

        foreach (array_slice($improvements, 0, 5) as $improvement) {
            $this->MultiCell(0, 8, '• ' . $improvement['text'] . ' (' . number_format($improvement['rating'], 1) . ')', 0, 'L');
        }
    }

    private function addComparisonSection($current_results, $previous_results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Comparative Analysis', 0, 1);

        // Create comparison table
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(80, 10, 'Category', 1, 0, 'C');

        // Headers for each assessment
        $this->Cell(30, 10, 'Current', 1, 0, 'C');
        foreach ($previous_results as $index => $prev) {
            $label = 'Previous ' . ($index + 1);
            $this->Cell(30, 10, $label, 1, 0, 'C');
        }
        $this->Ln();

        // Calculate and display averages by topic
        $this->SetFont('helvetica', '', 10);
        foreach ($current_results as $topic_name => $topic) {
            $current_avg = $this->calculateTopicAverage($topic);

            $this->Cell(80, 8, $topic_name, 1, 0);
            $this->Cell(30, 8, number_format($current_avg, 2), 1, 0, 'C');

            foreach ($previous_results as $prev) {
                $prev_avg = isset($prev['results'][$topic_name]) ? 
                           $this->calculateTopicAverage($prev['results'][$topic_name]) : 0;
                $this->Cell(30, 8, number_format($prev_avg, 2), 1, 0, 'C');
            }
            $this->Ln();
        }

        $this->Ln(10);
        $this->addTrendsAnalysis($current_results, $previous_results);
    }

    private function addTrendsAnalysis($current_results, $previous_results) {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Trends Analysis', 0, 1);

        $this->SetFont('helvetica', '', 11);

        // Analyze improvements
        $improvements = [];
        $declines = [];

        foreach ($current_results as $topic_name => $topic) {
            $current_avg = $this->calculateTopicAverage($topic);

            if (isset($previous_results[0]['results'][$topic_name])) {
                $prev_avg = $this->calculateTopicAverage($previous_results[0]['results'][$topic_name]);
                $difference = $current_avg - $prev_avg;

                if ($difference > 0.2) {
                    $improvements[] = [
                        'topic' => $topic_name,
                        'change' => $difference
                    ];
                } elseif ($difference < -0.2) {
                    $declines[] = [
                        'topic' => $topic_name,
                        'change' => $difference
                    ];
                }
            }
        }

        // Show improvements
        if (!empty($improvements)) {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'Notable Improvements:', 0, 1);
            $this->SetFont('helvetica', '', 11);
            foreach ($improvements as $improvement) {
                $this->MultiCell(0, 8, 
                    sprintf('• %s (+%.1f)', $improvement['topic'], $improvement['change']),
                    0, 'L'
                );
            }
        }

        // Show declines
        if (!empty($declines)) {
            $this->Ln(5);
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'Areas of Decline:', 0, 1);
            $this->SetFont('helvetica', '', 11);
            foreach ($declines as $decline) {
                $this->MultiCell(0, 8, 
                    sprintf('• %s (%.1f)', $decline['topic'], $decline['change']),
                    0, 'L'
                );
            }
        }
    }

    private function addOverallSummary($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Overall Performance Summary', 0, 1);

        // Calculate overall statistics
        $total_assessments = count($results);
        $latest_rating = end($results)->average_rating;
        $first_rating = reset($results)->average_rating;
        $improvement = $latest_rating - $first_rating;

        // Display summary
        $this->SetFont('helvetica', '', 11);
        $summary = sprintf(
            "Based on %d completed assessments:\n\n" .
            "• Initial Rating: %.2f\n" .
            "• Current Rating: %.2f\n" .
            "• Overall Improvement: %.2f\n" .
            "• Total Assessors: %d\n",
            $total_assessments,
            $first_rating,
            $latest_rating,
            $improvement,
            array_sum(array_column($results, 'total_assessors'))
        );

        $this->MultiCell(0, 10, $summary, 0, 'L');
    }

    private function addPerformanceTrends($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Performance Trends', 0, 1);

        // Create data for chart
        $dates = array_map(function($r) {
            return date('M Y', strtotime($r->created_at));
        }, $results);

        $ratings = array_map(function($r) {
            return $r->average_rating;
        }, $results);

        // Create bar chart
        $width = 160;
        $height = 80;
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(0, 123, 255);

        // Draw bars
        $bar_width = $width / count($results);
        foreach ($ratings as $i => $rating) {
            $bar_height = ($rating / 5) * $height;
            $x = $this->GetX() + ($i * $bar_width);
            $y = $this->GetY() + $height - $bar_height;

            $this->Rect($x, $y, $bar_width - 2, $bar_height, 'DF');

            // Add label
            $this->SetXY($x, $y + $height + 5);
            $this->SetFont('helvetica', '', 8);
            $this->Cell($bar_width - 2, 5, $dates[$i], 0, 0, 'C');
        }
    }

    private function addTopicAnalysis($results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Topic Analysis', 0, 1);

        // Group by topics
        $topics = [];
        foreach ($results as $result) {
            foreach ($result->topics as $topic) {
                if (!isset($topics[$topic->topic_name])) {
                    $topics[$topic->topic_name] = [];
                }
                $topics[$topic->topic_name][] = $topic->average_rating;
            }
        }

        // Display topic trends
        foreach ($topics as $topic_name => $ratings) {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, $topic_name, 0, 1);

            $this->SetFont('helvetica', '', 10);
            $trend = end($ratings) - reset($ratings);
            $trend_text = sprintf(
                "Initial: %.2f, Current: %.2f (%.2f %s)",
                reset($ratings),
                end($ratings),
                abs($trend),
                $trend >= 0 ? 'improvement' : 'decline'
            );
            $this->Cell(0, 8, $trend_text, 0, 1);
        }
    }

    private function addTrendsSection($current_results, $previous_results) {
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Performance Trends Analysis', 0, 1);

        // Compare current with previous results
        if (empty($previous_results)) {
            $this->SetFont('helvetica', '', 11);
            $this->Cell(0, 10, 'No previous assessment data available for comparison.', 0, 1);
            return;
        }

        $this->SetFont('helvetica', '', 11);

        // Calculate overall trends
        $current_avg = $this->calculateOverallAverage($current_results);
        $prev_avg = $this->calculateOverallAverage($previous_results[0]['results']);
        $difference = $current_avg - $prev_avg;

        // Display overall trend
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Overall Performance Trend:', 0, 1);
        $this->SetFont('helvetica', '', 11);
        $trend_text = sprintf(
            "Previous Average: %.2f\nCurrent Average: %.2f\nChange: %.2f (%s)",
            $prev_avg,
            $current_avg,
            abs($difference),
            $difference >= 0 ? 'improvement' : 'decline'
        );
        $this->MultiCell(0, 8, $trend_text, 0, 'L');
        $this->Ln(5);

        // Draw trend graph
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Performance Trend Graph', 0, 1);

        // Prepare data for graph
        $trend_data = [];

        // Add previous assessment data
        foreach ($previous_results as $prev) {
            $trend_data[] = [
                'date' => $prev['created_at'],
                'rating' => $this->calculateOverallAverage($prev['results'])
            ];
        }

        // Add current assessment data
        $trend_data[] = [
            'date' => date('Y-m-d'), // Current date
            'rating' => $current_avg
        ];

        // Draw the graph
        $this->drawTrendGraph($trend_data);
        $this->Ln(20);

        // Display topic-wise trends
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Topic-wise Analysis:', 0, 1);

        // Draw topic comparison graphs
        foreach ($current_results as $topic_name => $topic) {
            if (isset($previous_results[0]['results'][$topic_name])) {
                $this->SetFont('helvetica', 'B', 11);
                $this->Cell(0, 8, $topic_name, 0, 1);

                // Prepare topic data
                $topic_data = [];
                foreach ($previous_results as $prev) {
                    if (isset($prev['results'][$topic_name])) {
                        $topic_data[] = [
                            'date' => $prev['created_at'],
                            'rating' => $this->calculateTopicAverage($prev['results'][$topic_name])
                        ];
                    }
                }

                // Add current topic data
                $topic_data[] = [
                    'date' => date('Y-m-d'),
                    'rating' => $this->calculateTopicAverage($topic)
                ];

                // Draw mini graph for topic
                $this->drawTopicGraph($topic_data, $topic_name);
                $this->Ln(5);
            }
        }
    }

    private function drawTopicGraph($data, $topic_name) {
        if (empty($data)) {
            $this->Cell(0, 10, 'No trend data available', 0, 1);
            return;
        }

        // Graph dimensions (smaller than main graph)
        $graph_x = 30;
        $graph_y = $this->GetY() + 40; // Position based on current Y
        $graph_width = 120;
        $graph_height = 60;

        // Set drawing colors
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(0, 123, 255);
        $this->SetLineWidth(0.2);

        // Draw axes
        $this->Line($graph_x, $graph_y, $graph_x, $graph_y - $graph_height);
        $this->Line($graph_x, $graph_y, $graph_x + $graph_width, $graph_y);

        // Plot points and lines
        $count = count($data);
        $point_spacing = $graph_width / ($count > 1 ? $count - 1 : 1);
        $scale_factor = $graph_height / 5;

        // Draw grid lines and labels
        $this->SetDrawColor(200, 200, 200);
        for ($i = 0; $i <= 5; $i++) {
            $y = $graph_y - ($i * $scale_factor);
            // Grid line
            $this->Line($graph_x, $y, $graph_x + $graph_width, $y);
            // Label
            $this->SetFont('helvetica', '', 8);
            $this->SetXY($graph_x - 15, $y - 3);
            $this->Cell(10, 6, $i, 0, 0, 'R');
        }

        // Reset drawing color for points and lines
        $this->SetDrawColor(0, 123, 255);
        $this->SetFillColor(0, 123, 255);

        // Plot points and connect them
        $points = [];
        foreach ($data as $i => $point) {
            $x = $graph_x + ($i * $point_spacing);
            $y = $graph_y - ($point['rating'] * $scale_factor);
            $points[] = [$x, $y];

            // Draw point
            $this->Circle($x, $y, 1.5, 0, 360, 'DF');

            // Add date label
            $this->SetFont('helvetica', '', 7);
            $this->SetXY($x - 10, $graph_y + 2);
            $this->Cell(20, 5, date('M y', strtotime($point['date'])), 0, 0, 'C');
        }

        // Connect points with lines
        if (count($points) > 1) {
            $this->SetLineWidth(0.5);
            for ($i = 0; $i < count($points) - 1; $i++) {
                $this->Line(
                    $points[$i][0], $points[$i][1],
                    $points[$i + 1][0], $points[$i + 1][1]
                );
            }
        }

        // Reset styles
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(0, 0, 0);

        // Move Y position for next element
        $this->SetY($graph_y + 15);
    }

    private function calculateOverallAverage($results) {
        $total = 0;
        $count = 0;

        foreach ($results as $topic) {
            foreach ($topic['sections'] as $section) {
                foreach ($section['questions'] as $question) {
                    if (isset($question['average_rating'])) {
                        $total += $question['average_rating'];
                        $count++;
                    }
                }
            }
        }

        return $count > 0 ? $total / $count : 0;
    }

    private function calculateTopicAverage($topic) {
        $total = 0;
        $count = 0;

        foreach ($topic['sections'] as $section) {
            foreach ($section['questions'] as $question) {
                if (isset($question['average_rating'])) {
                    $total += $question['average_rating'];
                    $count++;
                }
            }
        }

        return $count > 0 ? $total / $count : 0;
    }

    public function generateOverallReport($user_id) {
        // Get user data
        $user_manager = Assessment_360_User_Manager::get_instance();
        $assessment = Assessment_360_Assessment::get_instance();

        $user = $user_manager->get_user($user_id);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Get all assessments for this user
        $all_assessments = $assessment->get_user_all_assessment_results($user_id);
        if (empty($all_assessments)) {
            throw new Exception('No assessment data found');
        }

        // Set document info
        $this->SetTitle("360° Overall Performance Report - {$user->first_name} {$user->last_name}");
        $this->custom_header_title = "Overall Performance Report - {$user->first_name} {$user->last_name}";

        // Add cover page
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 24);
        $this->Cell(0, 20, '360° Overall Performance Report', 0, 1, 'C');

        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, $user->first_name . ' ' . $user->last_name, 0, 1, 'C');

        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');

        // Add summary section
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Performance Summary', 0, 1);

        // Calculate overall statistics
        $total_assessments = count($all_assessments);
        $overall_trend = [];

        foreach ($all_assessments as $assessment_data) {
            $overall_trend[] = [
                'date' => $assessment_data->created_at,
                'rating' => $assessment_data->average_rating
            ];
        }

        // Display statistics
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, "Total Assessments Completed: {$total_assessments}", 0, 1);

        if (!empty($overall_trend)) {
            $first = reset($overall_trend);
            $last = end($overall_trend);
            $improvement = $last['rating'] - $first['rating'];

            $this->Cell(0, 10, "Initial Rating: " . number_format($first['rating'], 2), 0, 1);
            $this->Cell(0, 10, "Current Rating: " . number_format($last['rating'], 2), 0, 1);
            $this->Cell(0, 10, "Overall Change: " . ($improvement >= 0 ? '+' : '') . number_format($improvement, 2), 0, 1);
        }

        // Add trend graph
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Performance Trend', 0, 1);

        // Draw trend graph
        $this->drawTrendGraph($overall_trend);
    }

    protected function drawTrendGraph($data) {
        if (empty($data)) {
            $this->Cell(0, 10, 'No trend data available', 0, 1);
            return;
        }

        // Graph dimensions
        $graph_x = 30;
        $graph_y = 60;
        $graph_width = 150;
        $graph_height = 100;

        // Set drawing colors
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(0, 123, 255);
        $this->SetLineWidth(0.2);

        // Draw axes
        $this->Line($graph_x, $graph_y, $graph_x, $graph_y - $graph_height);
        $this->Line($graph_x, $graph_y, $graph_x + $graph_width, $graph_y);

        // Plot points and lines
        $count = count($data);
        $point_spacing = $graph_width / ($count > 1 ? $count - 1 : 1);
        $scale_factor = $graph_height / 5; // Assuming max rating is 5

        // Draw grid lines and labels
        $this->SetDrawColor(200, 200, 200);
        for ($i = 0; $i <= 5; $i++) {
            $y = $graph_y - ($i * $scale_factor);
            // Grid line
            $this->Line($graph_x, $y, $graph_x + $graph_width, $y);
            // Label
            $this->SetFont('helvetica', '', 8);
            $this->SetXY($graph_x - 15, $y - 3);
            $this->Cell(10, 6, $i, 0, 0, 'R');
        }

        // Reset drawing color for points and lines
        $this->SetDrawColor(0, 123, 255);
        $this->SetFillColor(0, 123, 255);

        // Plot points and connect them
        $points = [];
        foreach ($data as $i => $point) {
            $x = $graph_x + ($i * $point_spacing);
            $y = $graph_y - ($point['rating'] * $scale_factor);
            $points[] = [$x, $y];

            // Draw point
            $this->Circle($x, $y, 1.5, 0, 360, 'DF');

            // Add date label
            $this->SetFont('helvetica', '', 8);
            $this->SetXY($x - 10, $graph_y + 5);
            $this->Cell(20, 5, date('M Y', strtotime($point['date'])), 0, 0, 'C');
        }

        // Connect points with lines
        if (count($points) > 1) {
            $this->SetLineWidth(0.5);
            for ($i = 0; $i < count($points) - 1; $i++) {
                $this->Line(
                    $points[$i][0], $points[$i][1],
                    $points[$i + 1][0], $points[$i + 1][1]
                );
            }
        }

        // Reset styles
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(0, 0, 0);
    }

    protected function setGraphColors($colors = []) {
        $this->SetDrawColor($colors[0] ?? 0, $colors[1] ?? 123, $colors[2] ?? 255);
        $this->SetFillColor($colors[0] ?? 0, $colors[1] ?? 123, $colors[2] ?? 255);
    }

    protected function resetGraphColors() {
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
    }

    protected function drawPoint($x, $y, $size = 1.5) {
        $this->Circle($x, $y, $size);
        $this->SetFillColor(255, 255, 255);
        $this->Circle($x, $y, $size * 0.5);
        $this->SetFillColor(0, 123, 255);
    }

    public function Circle($x0, $y0, $r, $angstr = 0, $angend = 360, $style = '', $line_style = array(), $fill_color = array(), $nc = 2) {
        parent::Circle($x0, $y0, $r, $angstr, $angend, $style, $line_style, $fill_color, $nc);
    }


    
}
