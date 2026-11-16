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
