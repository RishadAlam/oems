<?php

declare(strict_types=1);

namespace OEMS\App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class CmsBannerPresenter
{
    public function present(array $banner, DateTimeImmutable $now, DateTimeZone $timezone): array
    {
        $now = $now->setTimezone($timezone);
        $now = $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            (int) $now->format('s'),
            0,
        );
        [$startsAt, $startsInvalid] = $this->parseBound($banner['starts_at'] ?? null, $timezone);
        [$endsAt, $endsInvalid] = $this->parseBound($banner['ends_at'] ?? null, $timezone);
        $scheduleValid = !$startsInvalid
            && !$endsInvalid
            && !($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt);

        $schedule = $scheduleValid
            ? [
                'valid' => true,
                'fallback' => null,
                'starts' => $this->presentBound($startsAt, 'Immediately'),
                'ends' => $this->presentBound($endsAt, 'No end date'),
            ]
            : [
                'valid' => false,
                'fallback' => 'Schedule unavailable',
                'starts' => null,
                'ends' => null,
            ];

        $delivery = $this->delivery(
            $this->activeState($banner['is_active'] ?? null),
            $this->homeEligible($banner),
            $scheduleValid,
            $startsAt,
            $endsAt,
            $now,
        );

        return array_merge($banner, [
            'schedule' => $schedule,
            'delivery' => $delivery,
        ]);
    }

    /** @return array{0: ?DateTimeImmutable, 1: bool} */
    private function parseBound(mixed $value, DateTimeZone $timezone): array
    {
        if ($value === null) {
            return [null, false];
        }

        if (!is_string($value)) {
            return [null, true];
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if ($date === false || $hasErrors || $date->format('Y-m-d H:i:s') !== $value) {
            return [null, true];
        }

        return [$date, false];
    }

    /** @return array{iso: ?string, display: string} */
    private function presentBound(?DateTimeImmutable $date, string $missingLabel): array
    {
        if ($date === null) {
            return ['iso' => null, 'display' => $missingLabel];
        }

        return [
            'iso' => $date->format('Y-m-d\TH:i:sP'),
            'display' => $date->format('M j, Y, g:i A'),
        ];
    }

    /** @return array{label: string, tone: string} */
    private function delivery(
        string $activeState,
        bool $homeEligible,
        bool $scheduleValid,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        DateTimeImmutable $now,
    ): array {
        if ($activeState === 'disabled') {
            return ['label' => 'Disabled', 'tone' => 'neutral'];
        }

        if ($activeState !== 'enabled' || !$homeEligible || !$scheduleValid) {
            return ['label' => 'Unknown', 'tone' => 'neutral'];
        }

        if ($startsAt !== null && $startsAt > $now) {
            return ['label' => 'Scheduled', 'tone' => 'warning'];
        }

        if ($endsAt !== null && $endsAt < $now) {
            return ['label' => 'Ended', 'tone' => 'neutral'];
        }

        return ['label' => 'Live', 'tone' => 'success'];
    }

    private function activeState(mixed $value): string
    {
        return match (true) {
            $value === 1, $value === '1', $value === true => 'enabled',
            $value === 0, $value === '0', $value === false => 'disabled',
            default => 'unknown',
        };
    }

    private function homeEligible(array $banner): bool
    {
        if (!array_key_exists('location', $banner)) {
            return false;
        }

        $location = $banner['location'];

        return is_string($location) && strcasecmp(rtrim($location, ' '), 'home') === 0;
    }
}
