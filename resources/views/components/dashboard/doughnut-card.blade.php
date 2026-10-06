@props(['title', 'url', 'id'])

<div class="card-custom p-4" data-chart-card>
    <h2 class="card-heading mb-4">{{ $title }}</h2>
    <div class="d-flex flex-column flex-sm-row align-items-center gap-4">
        <div class="chart-wrap">
            <canvas id="{{ $id }}" data-chart="doughnut" data-url="{{ $url }}" aria-label="{{ $title }}" role="img"></canvas>
            <div class="chart-center">
                <strong data-chart-total>0</strong>
                <span data-chart-center-label>&nbsp;</span>
            </div>
        </div>
        <ul class="chart-legend flex-grow-1 w-100" data-chart-legend></ul>
    </div>
</div>
