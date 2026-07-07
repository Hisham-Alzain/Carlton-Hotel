<?php
namespace App\Actions\Staff;

use App\Services\StaffService;

class CreateStaffAction
{
    public function __construct(private readonly StaffService $staffService) {}

    public function handle(array $data): array
    {
        // Role existence validated by CreateStaffRequest (exists:roles,name)
        return $this->staffService->createFromPreset($data);
    }
}
