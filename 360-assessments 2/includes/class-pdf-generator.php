<?php

require_once(__DIR__ . '/../libs/tcpdf/tcpdf.php');


class Assessment_360_PDF_Generator extends TCPDF{
    protected $organization_name;
    protected $organization_logo_path;

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $organization_name = '', $organization_logo_path = ''){
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);
        $this->organization_name = $organization_name;
        $this->organization_logo_path = $organization_logo_path;
    }

    public function CoverPage($title, $period, $assessee_name){
        $this->AddPage();
        if ($this->organization_logo_path && file_exists($this->organization_logo_path)) {
            $this->Image($this->organization_logo_path, 75, 30, 60, '', '', '', '', false, 300);
        }
        $this->SetFont('helvetica', 'B', 22);
        $this->SetY(100);
        $this->Cell(0, 15, $this->organization_name, 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell(0, 12, $title, 0, 1, 'C');
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 10, "Assessment Period: " . $period, 0, 1, 'C');
        $this->Cell(0, 10, "Assessee: " . $assessee_name, 0, 1, 'C');
    }

    public function SummaryPage($intro, $assessor_counts, $group_averages){
        $this->AddPage();
        $this->SetFont('helvetica', '', 12);
        $this->MultiCell(0, 10, $intro, 0, 'L', false, 1);
        $this->Ln(6);

        $this->SetFont('helvetica', 'B', 13);
        $this->Cell(0, 10, 'Number of Assessors by Group', 0, 1, 'L');
        $this->SetFont('helvetica', '', 11);
        $tbl = '<table border="1" cellpadding="3"><tr>';
        foreach ($assessor_counts as $group => $count) {
            $tbl .= '<th>' . htmlspecialchars($group) . '</th>';
        }
        $tbl .= '</tr><tr>';
        foreach ($assessor_counts as $count) {
            $tbl .= '<td align="center">' . intval($count) . '</td>';
        }
        $tbl .= '</tr></table>';
        $this->writeHTML($tbl, true, false, false, false, '');

        $this->Ln(4);
        $this->SetFont('helvetica', 'B', 13);
        $this->Cell(0, 10, 'Average Score by Group', 0, 1, 'L');
        $this->SetFont('helvetica', '', 11);
        $tbl = '<table border="1" cellpadding="3"><tr>';
        foreach ($group_averages as $group => $value) {
            $tbl .= '<th>' . htmlspecialchars($group) . '</th>';
        }
        $tbl .= '</tr><tr>';
        foreach ($group_averages as $value) {
            $tbl .= '<td align="center">' . number_format($value, 2) . '</td>';
        }
        $tbl .= '</tr></table>';
        $this->writeHTML($tbl, true, false, false, false, '');
    }

    public function DetailedTable($questions_data, $user_groups){
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Detailed Results by Question and Group', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);

        $tbl = '<table border="1" cellpadding="3">
            <tr>
                <th>Topic</th>
                <th>Section</th>
                <th>Question</th>';
        foreach ($user_groups as $group) {
            $tbl .= '<th>' . htmlspecialchars($group) . '</th>';
        }
        $tbl .= '</tr>';

        foreach ($questions_data as $row) {
            $tbl .= '<tr>';
            $tbl .= '<td>' . htmlspecialchars($row['topic']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['section']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['question']) . '</td>';
            foreach ($user_groups as $group) {
                $val = array_key_exists($group, $row['group_values']) ? $row['group_values'][$group] : '';
                $tbl .= '<td align="center">' . (is_numeric($val) ? number_format($val, 2) : $val) . '</td>';
            }
            $tbl .= '</tr>';
        }

        $tbl .= '</table>';
        $this->writeHTML($tbl, true, false, false, false, '');
    }

    public function AddChartsGrid($charts) {
        $chartsPerRow = 3;
        $chartsPerCol = 3;
        $chartWidth = 60;  // ~200px
        $chartHeight = 60; // ~200px
        $marginX = 10;
        $marginY = 15;

        $pageWidth = $this->getPageWidth() - $this->lMargin - $this->rMargin;
        $startX = $this->lMargin;
        $startY = $this->GetY() + 5;

        $i = 0;
        foreach ($charts as $idx => $chart) {
            // New page for each full grid
            if ($i % ($chartsPerRow * $chartsPerCol) == 0) {
                $this->AddPage();
                $startY = $this->GetY() + 5;
            }
            $row = intval(($i % ($chartsPerRow * $chartsPerCol)) / $chartsPerRow);
            $col = ($i % $chartsPerRow);
            $x = $startX + $col * ($chartWidth + $marginX);
            $y = $startY + $row * ($chartHeight + $marginY + 8);

            // Draw chart label
            $this->SetFont('helvetica', 'B', 10);
            $this->SetXY($x, $y);
            $this->Cell($chartWidth, 7, $chart['label'], 0, 2, 'C');

            // Draw chart image
            if (!empty($chart['img'])) {
                $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $chart['img']);
                $imageContent = base64_decode($base64);
                if ($imageContent !== false) {
                    $this->Image('@' . $imageContent, $x, $y + 8, $chartWidth, $chartHeight, 'PNG');
                } else {
                    $this->SetFont('helvetica', '', 9);
                    $this->SetXY($x, $y + 8);
                    $this->SetTextColor(220, 0, 0);
                    $this->MultiCell($chartWidth, 8, 'Chart image could not be decoded.', 0, 'C', false, 1);
                    $this->SetTextColor(0, 0, 0);
                }
            }
            $i++;
        }
    }
    public function AddCharts($charts) {
        foreach ($charts as $chart) {
            $this->AddPage();
            $this->SetFont('helvetica', 'B', 13);
            $this->Cell(0, 10, $chart['label'], 0, 1, 'L');
            if (!empty($chart['img'])) {
                $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $chart['img']);
                $imageContent = base64_decode($base64);
                if ($imageContent !== false) {
                    $this->Image('@' . $imageContent, '', '', 170, 80, 'PNG');
                } else {
                    $this->SetFont('helvetica', '', 10);
                    $this->SetTextColor(220, 0, 0);
                    $this->MultiCell(0, 8, 'Chart image data could not be decoded.', 0, 'L', false, 1);
                    $this->SetTextColor(0, 0, 0);
                }
            }
            $this->Ln(8);
        }
    }

    public function IndividualResponsesTable($responses){
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 13);
        $this->Cell(0, 10, 'All Individual Responses', 0, 1, 'L');
        $this->SetFont('helvetica', '', 9);

        $tbl = '<table border="1" cellpadding="2">
            <tr>
                <th>Topic</th>
                <th>Section</th>
                <th>Question</th>
                <th>Group</th>
                <th>Value</th>
                <th>Comment</th>
            </tr>';
        foreach ($responses as $row) {
            $tbl .= '<tr>';
            $tbl .= '<td>' . htmlspecialchars($row['topic']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['section']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['question']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['group']) . '</td>';
            $tbl .= '<td align="center">' . (is_numeric($row['value']) ? number_format($row['value'], 2) : $row['value']) . '</td>';
            $tbl .= '<td>' . htmlspecialchars($row['comment']) . '</td>';
            $tbl .= '</tr>';
        }
        $tbl .= '</table>';
        $this->writeHTML($tbl, true, false, false, false, '');
    }
}