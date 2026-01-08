<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = User::where('role', Role::Employee)->get();
        $agents = User::where('role', Role::Agent)->get();

        // Open tickets - unassigned
        Ticket::create([
            'subject' => 'Cannot access shared drive',
            'description' => 'I\'m getting an "access denied" error when trying to open files on the S: drive. This started happening after yesterday\'s system update.',
            'category' => TicketCategory::Access,
            'severity' => 3,
            'status' => TicketStatus::Open,
            'created_by' => $employees[0]->id,
        ]);

        Ticket::create([
            'subject' => 'Keyboard not responding',
            'description' => 'My keyboard suddenly stopped working this morning. I\'ve tried unplugging and reconnecting it but no response. The NumLock light doesn\'t turn on.',
            'category' => TicketCategory::Hardware,
            'severity' => 4,
            'status' => TicketStatus::Open,
            'created_by' => $employees[1]->id,
        ]);

        Ticket::create([
            'subject' => 'VPN connection dropping frequently',
            'description' => 'VPN disconnects every 10-15 minutes when working from home. Have to manually reconnect each time. Internet connection is stable.',
            'category' => TicketCategory::Network,
            'severity' => 4,
            'status' => TicketStatus::Open,
            'created_by' => $employees[2]->id,
        ]);

        // In Progress tickets - assigned
        Ticket::create([
            'subject' => 'Password reset not working',
            'description' => 'The password reset link I received expires before I can use it. I\'ve requested 3 resets in the last hour and all expired within seconds.',
            'category' => TicketCategory::Access,
            'severity' => 5,
            'status' => TicketStatus::InProgress,
            'created_by' => $employees[3]->id,
            'assigned_to' => $agents[0]->id,
        ]);

        Ticket::create([
            'subject' => 'Monitor flickering intermittently',
            'description' => 'My second monitor has been flickering on and off throughout the day. Sometimes it works fine for hours, then starts flickering again.',
            'category' => TicketCategory::Hardware,
            'severity' => 2,
            'status' => TicketStatus::InProgress,
            'created_by' => $employees[4]->id,
            'assigned_to' => $agents[1]->id,
        ]);

        Ticket::create([
            'subject' => 'Email attachments not downloading',
            'description' => 'When I click to download attachments in Outlook, I get an error message "The operation failed." This happens with all attachment types.',
            'category' => TicketCategory::Bug,
            'severity' => 3,
            'status' => TicketStatus::InProgress,
            'created_by' => $employees[0]->id,
            'assigned_to' => $agents[2]->id,
        ]);

        // Resolved tickets
        Ticket::create([
            'subject' => 'Need access to HR portal',
            'description' => 'I just transferred to the HR department and need access to the employee portal to process new hire paperwork.',
            'category' => TicketCategory::Access,
            'severity' => 3,
            'status' => TicketStatus::Resolved,
            'created_by' => $employees[1]->id,
            'assigned_to' => $agents[0]->id,
        ]);

        Ticket::create([
            'subject' => 'Printer jamming constantly',
            'description' => 'The printer on the 3rd floor keeps jamming. I\'ve cleared it multiple times but papers keep getting stuck in the same spot.',
            'category' => TicketCategory::Hardware,
            'severity' => 2,
            'status' => TicketStatus::Resolved,
            'created_by' => $employees[2]->id,
            'assigned_to' => $agents[1]->id,
        ]);

        Ticket::create([
            'subject' => 'Slow WiFi in conference room B',
            'description' => 'WiFi connection in conference room B is extremely slow. Speed test shows 2 Mbps when other areas get 100+ Mbps.',
            'category' => TicketCategory::Network,
            'severity' => 3,
            'status' => TicketStatus::Resolved,
            'created_by' => $employees[3]->id,
            'assigned_to' => $agents[2]->id,
        ]);

        // Closed tickets
        Ticket::create([
            'subject' => 'Calendar sync issues between devices',
            'description' => 'Appointments created on my phone don\'t show up on my computer and vice versa. Using Outlook on both devices.',
            'category' => TicketCategory::Bug,
            'severity' => 2,
            'status' => TicketStatus::Closed,
            'created_by' => $employees[4]->id,
            'assigned_to' => $agents[0]->id,
        ]);

        Ticket::create([
            'subject' => 'Request new software license',
            'description' => 'I need a license for Adobe Creative Cloud for my design work. Current trial expires in 3 days.',
            'category' => TicketCategory::Other,
            'severity' => 3,
            'status' => TicketStatus::Closed,
            'created_by' => $employees[0]->id,
            'assigned_to' => $agents[1]->id,
        ]);

        Ticket::create([
            'subject' => 'Mobile hotspot not connecting',
            'description' => 'Company-issued mobile hotspot won\'t connect to my laptop. Light is on but no network appears in available connections.',
            'category' => TicketCategory::Network,
            'severity' => 4,
            'status' => TicketStatus::Closed,
            'created_by' => $employees[1]->id,
            'assigned_to' => $agents[2]->id,
        ]);

        // Additional tickets for variety
        Ticket::create([
            'subject' => 'Dashboard showing incorrect data',
            'description' => 'The sales dashboard is displaying last month\'s numbers instead of current month. Refreshing doesn\'t help.',
            'category' => TicketCategory::Bug,
            'severity' => 4,
            'status' => TicketStatus::InProgress,
            'created_by' => $employees[2]->id,
            'assigned_to' => $agents[0]->id,
        ]);

        Ticket::create([
            'subject' => 'Need admin rights for software installation',
            'description' => 'I need temporary admin rights to install development tools for our new project. Specific tools: VS Code, Node.js, Docker.',
            'category' => TicketCategory::Access,
            'severity' => 3,
            'status' => TicketStatus::Open,
            'created_by' => $employees[3]->id,
        ]);

        Ticket::create([
            'subject' => 'Webcam not detected in Teams',
            'description' => 'Microsoft Teams can\'t detect my webcam even though it works in other apps. Already tried reinstalling Teams.',
            'category' => TicketCategory::Hardware,
            'severity' => 3,
            'status' => TicketStatus::Open,
            'created_by' => $employees[4]->id,
        ]);
    }
}
