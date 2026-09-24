@extends('layouts.admin')

@section('title', 'Application updates — '.__('app.name'))

@section('content')
    @php($updateState = $update['state'] ?? $update['status'] ?? null)
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div><p class="text-primary text-uppercase fw-bold small mb-2" style="letter-spacing:.12em">System control</p><h1 class="h2 fw-bold mb-1">Application updates</h1><p class="text-secondary mb-0">Stage and inspect a release ZIP before applying it.</p></div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-shield-lock me-1"></i>Operator-only access</span>
    </div>
    <div class="alert alert-info d-flex gap-3 align-items-start mb-4" role="status"><i class="bi bi-info-circle fs-4"></i><div><strong>No reinstall is required.</strong> The updater preserves <code>.env</code>, uploads and server-specific web files, while pending migrations may change the MySQL schema and records. Take a full, restorable MySQL backup and application-file copy before applying.</div></div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="card content-card h-100"><div class="card-body p-4 p-lg-5">
            <div class="d-flex gap-3 align-items-start mb-4"><span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary flex-shrink-0" style="width:48px;height:48px"><i class="bi bi-file-earmark-zip fs-4"></i></span><div><h2 class="h5 fw-bold mb-1">1. Stage a release ZIP</h2><p class="text-secondary mb-0">The manifest, file hashes and paths are checked before any application file changes.</p></div></div>
            <form method="POST" action="{{ route('admin.system-updates.store') }}" enctype="multipart/form-data" data-ajax class="vstack gap-3">
                @csrf
                <div><label class="form-label fw-semibold" for="updatePackage">Release ZIP</label><input class="form-control form-control-lg" type="file" id="updatePackage" name="package" accept=".zip,application/zip" required><div class="form-text">Maximum 100 MB, subject to the server PHP upload limit. Build the release with the documented package command.</div></div>
                <div><label class="form-label fw-semibold" for="stageAccessKey">Private update key</label><input class="form-control" type="password" id="stageAccessKey" name="access_key" minlength="64" maxlength="64" autocomplete="off" required><div class="form-text">Retrieve via SSH from <code class="user-select-all">{{ $accessTokenPath }}</code>. The key is never displayed here.</div></div>
                <div><button class="btn btn-primary px-4" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i>Validate &amp; stage ZIP</button></div>
            </form>
        </div></div></div>
        <div class="col-xl-5"><div class="card content-card h-100"><div class="card-body p-4 p-lg-5">
            <div class="d-flex gap-3 align-items-start mb-4"><span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-success-subtle text-success flex-shrink-0" style="width:48px;height:48px"><i class="bi bi-list-check fs-4"></i></span><div><h2 class="h5 fw-bold mb-1">2. Review &amp; apply</h2><p class="text-secondary mb-0">The server scheduler applies the release during a short maintenance window.</p></div></div>
            @if ($update)
                <div class="rounded-3 border bg-light p-3 mb-4" id="updateStatus" data-url="{{ route('admin.system-updates.status', $update['id']) }}" data-state="{{ $updateState }}">
                    <div class="d-flex justify-content-between gap-2 mb-2"><span class="text-secondary small">Release</span><strong>{{ $update['version'] ?? 'Unknown' }}</strong></div>
                    <div class="d-flex justify-content-between gap-2 mb-2"><span class="text-secondary small">Files</span><strong>{{ number_format($update['file_count'] ?? 0) }}</strong></div>
                    <div class="d-flex justify-content-between gap-2 mb-2"><span class="text-secondary small">Package size</span><strong>{{ number_format(($update['total_bytes'] ?? 0) / 1048576, 2) }} MB</strong></div>
                    <div class="d-flex justify-content-between gap-2"><span class="text-secondary small">Status</span><span class="badge {{ $updateState === 'completed' ? 'text-bg-success' : ($updateState === 'failed' ? 'text-bg-danger' : 'text-bg-primary') }}">{{ str($updateState ?? 'staged')->headline() }}</span></div>
                </div>
                @if (! empty($update['error']))<div class="alert alert-danger small">{{ $update['error'] }}<br><strong>Inspect server logs and restore from backup if necessary before retrying.</strong></div>@endif
                @if ($updateState === 'staged')
                    <form method="POST" action="{{ route('admin.system-updates.apply', $update['id']) }}" data-ajax class="vstack gap-3">
                        @csrf
                        <div><label class="form-label fw-semibold" for="applyAccessKey">Private update key</label><input class="form-control" type="password" id="applyAccessKey" name="access_key" minlength="64" maxlength="64" autocomplete="off" required></div>
                        <div><label class="form-label fw-semibold" for="backupReference">Full MySQL backup reference</label><input class="form-control" id="backupReference" name="backup_reference" placeholder="hPanel backup date or dump filename" maxlength="255" required></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_backup" value="1" id="confirmBackup" required><label class="form-check-label" for="confirmBackup">I verified a restorable full MySQL backup and application-file copy.</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_downtime" value="1" id="confirmDowntime" required><label class="form-check-label" for="confirmDowntime">I approve brief maintenance downtime.</label></div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-lightning-charge me-2"></i>Queue application update</button>
                    </form>
                @elseif (in_array($updateState, ['queued', 'running', 'applying'], true))
                    <p class="small text-secondary mb-0">The scheduler is applying the release. This page refreshes when its status changes.</p>
                @else
                    <a href="{{ route('admin.system-updates.index') }}" class="btn btn-outline-primary">Stage another release</a>
                @endif
            @else
                <div class="rounded-3 border text-center px-4 py-5 text-secondary"><i class="bi bi-archive fs-1 d-block mb-2"></i>No release staged yet. Upload a ZIP to inspect its contents.</div>
            @endif
        </div></div></div>
    </div>
    <div class="card content-card mt-4"><div class="card-body p-4"><h2 class="h6 fw-bold mb-3">What the updater protects</h2><div class="row g-3 small"><div class="col-md-4"><i class="bi bi-database-check text-success me-2"></i>No database reset or demo re-seeding</div><div class="col-md-4"><i class="bi bi-folder-check text-success me-2"></i>Uploaded photos and private storage</div><div class="col-md-4"><i class="bi bi-gear text-success me-2"></i><code>.env</code> and server web-root configuration</div></div></div></div>
@endsection

@push('scripts')
    <script>
        (() => {
            const status = document.getElementById('updateStatus');
            if (!status || !['queued', 'running', 'applying'].includes(status.dataset.state)) return;
            const initialState = status.dataset.state;
            const timer = window.setInterval(async () => {
                try {
                    const response = await fetch(status.dataset.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const payload = await response.json();
                    const latestState = payload.update?.state ?? payload.update?.status;
                    if (latestState && latestState !== initialState) {
                        window.clearInterval(timer);
                        window.location.reload();
                    }
                } catch (_) {
                    window.clearInterval(timer);
                }
            }, 10000);
        })();
    </script>
@endpush
