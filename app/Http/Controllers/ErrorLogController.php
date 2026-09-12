<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ErrorLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('error_log-view'), 403);

        $page_title = 'Error Logs';

        $logPath = storage_path('logs/errors');

        if (! File::exists($logPath)) {
            File::makeDirectory($logPath, 0755, true);
        }

        $files = collect(File::files($logPath))
            ->filter(function ($file) {
                return str_starts_with($file->getFilename(), 'error-')
                    && str_ends_with($file->getFilename(), '.log');
            })
            ->sortByDesc(function ($file) {
                return $file->getMTime();
            })
            ->values();

        $logs = collect();

        foreach ($files as $file) {
            $logs = $logs->merge(
                $this->parseLogFile($file->getPathname(), $file->getFilename())
            );
        }

        $logs = $this->filterLogs($logs, $request);

        $logs = $logs
            ->sortByDesc(function ($log) {
                return strtotime($log->date_time);
            })
            ->values();

        $logs = $this->paginateLogs($logs, $request);

        $fileNames = $files
            ->map(function ($file) {
                return $file->getFilename();
            })
            ->values();

        return view('logs.error', compact(
            'page_title',
            'logs',
            'fileNames'
        ));
    }

    private function parseLogFile(string $path, string $fileName): Collection
    {
        $content = File::get($path);

        $entries = collect();

        preg_match_all(
            '/\[(.*?)\]\s+\w+\.ERROR:\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\z)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $dateTime = $match[1] ?? null;
            $rawEntry = trim($match[2] ?? '');

            $message = $rawEntry;
            $context = [];

            /*
             * Try to separate message and JSON context.
             * Example:
             * A non-numeric value encountered ... {"exception":"...","file":"...","line":52}
             */
            if (preg_match('/^(.*?)\s+(\{"exception":.*\})\s*$/s', $rawEntry, $jsonMatch)) {
                $message = trim($jsonMatch[1] ?? '');
                $json = trim($jsonMatch[2] ?? '{}');

                $decoded = json_decode($json, true);

                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            /*
             * Normal JSON context values
             */
            $exceptionText = $context['exception'] ?? null;
            $errorFile = $context['file'] ?? null;
            $errorLine = $context['line'] ?? null;

            /*
             * Fallback: extract directly from raw log text
             * This works even if json_decode fails.
             */
            if (! $errorFile && preg_match('/"file"\s*:\s*"([^"]+)"/', $rawEntry, $fileMatch)) {
                $errorFile = stripcslashes($fileMatch[1]);
            }

            if (! $errorLine && preg_match('/"line"\s*:\s*(\d+)/', $rawEntry, $lineMatch)) {
                $errorLine = $lineMatch[1];
            }

            if (! $exceptionText && preg_match('/"exception"\s*:\s*"([^"]+)"/', $rawEntry, $exceptionMatch)) {
                $exceptionText = stripcslashes($exceptionMatch[1]);
            }

            /*
             * Fallback for Laravel exception text:
             * "... at /path/file.php:52"
             */
            if ((! $errorFile || ! $errorLine) && $exceptionText) {
                if (preg_match('/ at (.*?):(\d+)/', $exceptionText, $exceptionMatch)) {
                    $errorFile = $errorFile ?: ($exceptionMatch[1] ?? null);
                    $errorLine = $errorLine ?: ($exceptionMatch[2] ?? null);
                }
            }

            $entries->push((object) [
                'file_name' => $fileName,
                'date_time' => $dateTime,

                'message' => $message,
                'exception' => $exceptionText ?: 'N/A',
                'error_file' => $errorFile ?: 'N/A',
                'line' => $errorLine ?: 'N/A',

                'url' => $context['url'] ?? $this->extractLogValue($rawEntry, 'url') ?? 'N/A',
                'method' => $context['method'] ?? $this->extractLogValue($rawEntry, 'method') ?? 'N/A',
                'ip' => $context['ip'] ?? $this->extractLogValue($rawEntry, 'ip') ?? 'N/A',

                'user_id' => $context['user_id'] ?? $this->extractLogValue($rawEntry, 'user_id'),
                'user_name' => $context['user_name'] ?? $this->extractLogValue($rawEntry, 'user_name'),
                'user_email' => $context['user_email'] ?? $this->extractLogValue($rawEntry, 'user_email'),

                'input' => $context['input'] ?? [],
                'trace' => $context['trace'] ?? '',
                'context' => $context,
            ]);
        }

        return $entries;
    }

    private function extractLogValue(string $rawEntry, string $key): mixed
    {
        if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*"([^"]*)"/', $rawEntry, $match)) {
            return stripcslashes($match[1]);
        }

        if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*(\d+)/', $rawEntry, $match)) {
            return $match[1];
        }

        return null;
    }

    private function filterLogs(Collection $logs, Request $request): Collection
    {
        if ($request->filled('file_name')) {
            $logs = $logs->filter(function ($log) use ($request) {
                return $log->file_name === $request->file_name;
            });
        }

        if ($request->filled('exception')) {
            $logs = $logs->filter(function ($log) use ($request) {
                return str_contains(
                    strtolower($log->exception),
                    strtolower($request->exception)
                );
            });
        }

        if ($request->filled('message')) {
            $logs = $logs->filter(function ($log) use ($request) {
                return str_contains(
                    strtolower($log->message),
                    strtolower($request->message)
                );
            });
        }

        if ($request->filled('url')) {
            $logs = $logs->filter(function ($log) use ($request) {
                return str_contains(
                    strtolower($log->url),
                    strtolower($request->url)
                );
            });
        }

        if ($request->filled('ip')) {
            $logs = $logs->filter(function ($log) use ($request) {
                return str_contains(
                    strtolower($log->ip),
                    strtolower($request->ip)
                );
            });
        }

        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();

            $logs = $logs->filter(function ($log) use ($startDate) {
                if (! $log->date_time) {
                    return false;
                }

                return Carbon::parse($log->date_time)->greaterThanOrEqualTo($startDate);
            });
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            $logs = $logs->filter(function ($log) use ($endDate) {
                if (! $log->date_time) {
                    return false;
                }

                return Carbon::parse($log->date_time)->lessThanOrEqualTo($endDate);
            });
        }

        return $logs->values();
    }

    private function paginateLogs(Collection $logs, Request $request): LengthAwarePaginator
    {
        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $items = $logs
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        return new LengthAwarePaginator(
            $items,
            $logs->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
