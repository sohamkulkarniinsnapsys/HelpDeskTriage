<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SimilarTicketController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketPageController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/health', function () {
    return response()->json([
        'name' => config('app.name', 'Helpdesk Triage'),
        'status' => 'ok',
        'version' => app()->version(),
    ]);
})->name('health');

// Authenticated UI routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Ticket pages
    Route::middleware('employee')->group(function () {
        Route::get('/tickets/create', [TicketPageController::class, 'create'])->name('tickets.create');
        Route::get('/tickets/my', [TicketPageController::class, 'myTickets'])->name('tickets.my');
    });

    Route::middleware('agent')->group(function () {
        Route::get('/tickets/all', [TicketPageController::class, 'allTickets'])->name('tickets.all');
    });

    Route::get('/tickets/{ticket}/detail', [TicketPageController::class, 'show'])->name('tickets.view');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// API + action routes protected by auth
Route::middleware('auth')->group(function () {
    // Ticket routes - accessible by both employees and agents
    Route::prefix('tickets')->name('tickets.')->group(function () {
        // List and view tickets (role-scoped)
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/statistics', [TicketController::class, 'statistics'])->name('statistics');
        Route::post('/similar', [SimilarTicketController::class, 'find'])->name('similar');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        
        // Create tickets
        Route::post('/', [TicketController::class, 'store'])->name('store');
        
        // Ticket attachments
        Route::get('/{ticket}/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
        Route::post('/{ticket}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    });

    // Agent-only ticket routes
    Route::middleware('agent')->prefix('tickets')->name('tickets.')->group(function () {
        Route::patch('/{ticket}', [TicketController::class, 'update'])->name('update');
        Route::patch('/{ticket}/assign', [TicketController::class, 'assign'])->name('assign');
        Route::patch('/{ticket}/status', [TicketController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])->name('destroy');
    });

    // Attachment routes
    Route::prefix('attachments')->name('attachments.')->group(function () {
        Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->name('download');
        Route::delete('/{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
