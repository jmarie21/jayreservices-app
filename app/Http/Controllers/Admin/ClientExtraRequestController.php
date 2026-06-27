<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientExtraRequestRequest;
use App\Models\ClientExtraRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ClientExtraRequestController extends Controller
{
    public function store(ClientExtraRequestRequest $request, User $client): RedirectResponse
    {
        $client->extraRequests()->create($this->normalizeLink($request->validated()));

        return back();
    }

    public function update(ClientExtraRequestRequest $request, User $client, ClientExtraRequest $extraRequest): RedirectResponse
    {
        $extraRequest->update($this->normalizeLink($request->validated()));

        return back();
    }

    public function destroy(User $client, ClientExtraRequest $extraRequest): RedirectResponse
    {
        $extraRequest->delete();

        return back();
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string|null>
     */
    private function normalizeLink(array $data): array
    {
        if (! empty($data['link']) && ! preg_match('/^https?:\/\//', $data['link'])) {
            $data['link'] = 'https://'.$data['link'];
        }

        return $data;
    }
}
