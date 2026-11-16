<?php

declare(strict_types=1);

final class WeeklyReportController
{
    private WeeklyReportService $reports;

    public function __construct()
    {
        $this->reports = new WeeklyReportService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $data = $this->reports->build(Auth::id(), $this->refFromRequest());

        view('weekly_report/index', array_merge($data, [
            'title' => 'Informe semanal',
            'currentNav' => 'weekly_report',
        ]));
    }

    public function pdf(): void
    {
        Auth::requireLogin();
        $data = $this->reports->build(Auth::id(), $this->refFromRequest());
        $appName = (string) app_config('name', 'LifeQuest');
        $colors = [
            'task' => [0.055, 0.431, 0.416],
            'sport' => [0.788, 0.416, 0.173],
            'habit' => [0.831, 0.627, 0.090],
            'goal' => [0.486, 0.514, 0.882],
            'milestone' => [0.471, 0.776, 0.690],
            'horizon' => [0.361, 0.396, 0.439],
        ];
        $dayNames = [
            1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
        ];
        $weekLabel = 'Semana ' . (ltrim((string) $data['weekNumber'], '0') ?: '0') . ' del ' . $data['weekYear'];

        $pdf = new SimplePdf();
        $pdf->banner(
            'Informe semanal',
            $appName . '  ·  ' . (string) $data['rangeLabel'],
            $weekLabel
        );
        $pdf->metrics([
            ['label' => 'Tareas hechas', 'value' => (string) (int) $data['tasksDone'], 'color' => $colors['task']],
            ['label' => 'Entrenamiento', 'value' => (string) (int) $data['trainingCount'], 'color' => $colors['sport']],
            ['label' => 'Hábitos hechos', 'value' => (string) (int) $data['habitsDoneCount'], 'color' => $colors['habit']],
        ]);
        $pdf->metrics([
            ['label' => 'Objetivos realizados', 'value' => (string) count($data['goals']), 'color' => $colors['goal']],
            ['label' => 'Hitos completados', 'value' => (string) count($data['milestones']), 'color' => $colors['milestone']],
            ['label' => 'Horizontes completados', 'value' => (string) count($data['horizons']), 'color' => $colors['horizon']],
        ]);

        $tasks = $data['tasks'];
        $pdf->section(
            'Plan semanal',
            $colors['task'],
            (string) ((int) $data['tasksDone']) . '/' . (string) count($tasks)
        );
        if ($tasks === []) {
            $pdf->empty('Ninguna esta semana.');
        } else {
            foreach ($tasks as $row) {
                $done = (int) ($row['is_done'] ?? 0) === 1;
                try {
                    $dow = (int) (new DateTimeImmutable((string) $row['task_date']))->format('N');
                } catch (Throwable) {
                    $dow = 0;
                }
                $kind = (($row['task_kind'] ?? '') === 'work') ? 'Laboral' : 'Personal';
                $meta = trim(
                    ($dayNames[$dow] ?? '') . ' '
                    . format_date((string) ($row['task_date'] ?? ''), 'd/m')
                    . ' · ' . $kind
                    . ($done ? ' · Hecha' : ' · Pendiente')
                );
                $pdf->item((string) $row['title'], $meta, $done);
            }
        }

        $training = $data['training'];
        $pdf->section('Entrenamiento', $colors['sport'], (string) (int) $data['trainingCount']);
        if ($training === []) {
            $pdf->empty('Nada registrado esta semana.');
        } else {
            foreach ($training as $row) {
                $pdf->item((string) $row['title'], (string) ($row['meta'] ?? ''), true);
            }
        }

        $habits = $data['habits'];
        $pdf->section('Hábitos y repeticiones', $colors['habit'], (string) (int) $data['habitsDoneCount']);
        if ($habits === []) {
            $pdf->empty('Sin hábitos marcados esta semana.');
        } else {
            foreach ($habits as $row) {
                $times = (int) ($row['times'] ?? 0);
                $qty = (float) ($row['qty_sum'] ?? 0);
                $meta = $times . ' ' . ($times === 1 ? 'vez' : 'veces');
                if ($qty > 0) {
                    $meta .= ' · acumulado ' . number_format($qty, $qty == floor($qty) ? 0 : 1, ',', '.');
                }
                $pdf->item((string) $row['title'], $meta, true);
            }
        }

        $goals = $data['goals'];
        $pdf->section('Objetivos completados', $colors['goal'], (string) count($goals));
        if ($goals === []) {
            $pdf->empty('Ninguno esta semana.');
        } else {
            foreach ($goals as $row) {
                $pdf->item((string) $row['title'], format_when($row['completed_at'] ?? ''), true);
            }
        }

        $milestones = $data['milestones'];
        $pdf->section('Hitos completados', $colors['milestone'], (string) count($milestones));
        if ($milestones === []) {
            $pdf->empty('Ninguno esta semana.');
        } else {
            foreach ($milestones as $row) {
                $bits = [];
                if (!empty($row['milestone_date'])) {
                    $bits[] = 'fecha ' . format_date((string) $row['milestone_date'], 'd/m');
                }
                $when = format_when($row['completed_at'] ?? '');
                if ($when !== '') {
                    $bits[] = $when;
                }
                $pdf->item((string) $row['title'], implode(' · ', $bits), true);
            }
        }

        $horizons = $data['horizons'];
        $pdf->section('Horizontes completados', $colors['horizon'], (string) count($horizons));
        if ($horizons === []) {
            $pdf->empty('Ninguno esta semana.');
        } else {
            foreach ($horizons as $row) {
                $pdf->item((string) $row['title'], format_when($row['completed_at'] ?? ''), true);
            }
        }

        $pdf->footer(
            $appName . ' · Informe semanal · Semana ' . $data['weekNumber'] . ' de ' . $data['weekYear']
        );

        $weekNum = str_pad((string) (int) ltrim((string) $data['weekNumber'], '0'), 2, '0', STR_PAD_LEFT);
        $filename = 'informe_semanal_lifequest_W' . $weekNum . '_' . $data['weekYear'] . '.pdf';
        $binary = $pdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($binary));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $binary;
        exit;
    }

    private function refFromRequest(): DateTimeImmutable
    {
        $raw = trim((string) (input('week') ?: now_local()->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return now_local();
        }
        try {
            $dt = new DateTimeImmutable($raw, now_local()->getTimezone());
        } catch (Throwable) {
            return now_local();
        }
        if ($dt->format('Y-m-d') !== $raw) {
            return now_local();
        }

        return $dt;
    }
}
