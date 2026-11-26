@extends('admin.layouts.app')

@section('title', __('Error Logs'))

@section('content')
    <div>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Error Logs</h1>

            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <form method="GET" action="{{ route('admin.logs.error') }}" class="mb-4">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search logs..." class="form-control d-inline w-auto">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-responsive small">
            @if($data->isNotEmpty())
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Message</th>
                            <th scope="col">JID</th>
                            <th scope="col">IP</th>
                            <th scope="col">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($data as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->subject }}</td>
                            <td>{{ $row->message }}</td>
                            <td>{{ $row->jid }}</td>
                            <td>{{ $row->ip }}</td>
                            <td>{{ $row->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No Records Found!</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            @endif

            {{ $data->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
