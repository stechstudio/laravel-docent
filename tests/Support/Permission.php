<?php

declare(strict_types=1);

namespace STS\Docent\Tests\Support;

/**
 * Stands in for a host application's backed permission enum — the shape where
 * the permission list is data and a single `Gate::before` bridge means
 * `Gate::has()` never sees any of it.
 */
enum Permission: string
{
    case ManageSettings = 'settings.manage';
    case ExportReports = 'reports.export';
}
