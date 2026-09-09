@props(['document', 'caseCode' => null])

<article
    {{ $attributes->class(['portal-doc-row']) }}
    data-doc-uid="{{ $document['uid'] }}"
>
    <div class="portal-doc-row__file">
        @php
            $extension = strtolower((string) ($document['extension'] ?? ''));
            $isPdf = $extension === 'pdf' || str_ends_with(strtolower((string) ($document['file_path'] ?? '')), '.pdf');
        @endphp
        <span @class(['portal-doc-row__icon', 'portal-doc-row__icon--pdf' => $isPdf || ! ($document['is_image'] ?? false)]) aria-hidden="true">
            @if ($document['is_image'])
                <flux:icon name="photo" class="size-4" />
            @else
                <img
                    src="{{ asset('icons/pdf.svg') }}"
                    alt=""
                    width="28"
                    height="28"
                    class="portal-doc-row__pdf-img"
                    decoding="async"
                />
            @endif
        </span>

        <div class="portal-doc-row__file-copy">
            @if ($caseCode)
                <p class="portal-doc-row__case">
                    {{ __('Caso:') }} <span class="font-mono">{{ $caseCode }}</span>
                </p>
            @endif

            @php
                [$documentTitlePrimary, $documentTitleSecondary] = \App\Support\PatientDocumentsPresentation::clinicalDocumentNameLines(
                    (string) ($document['document_name'] ?? '')
                );
            @endphp

            <h3 class="portal-doc-row__title">
                <span class="portal-doc-row__title-line">{{ $documentTitlePrimary }}</span>
                @if (filled($documentTitleSecondary))
                    <span class="portal-doc-row__title-line portal-doc-row__title-line--secondary">{{ $documentTitleSecondary }}</span>
                @endif
            </h3>

            @if (filled($document['upload_reason']))
                <p class="portal-doc-row__reason">
                    <span class="portal-doc-row__reason-label">{{ __('Razón de la carga:') }}</span>
                    {{ $document['upload_reason'] }}
                </p>
            @endif
        </div>
    </div>

    <div class="portal-doc-row__cell" data-label="{{ __('Categoría') }}">
        <span class="portal-doc-row__value">{{ $document['category'] ?: '—' }}</span>
    </div>

    <div class="portal-doc-row__cell" data-label="{{ __('Tipo') }}">
        <span class="portal-doc-row__value portal-doc-row__value--muted">{{ $document['types_label'] ?: '—' }}</span>
    </div>

    <div class="portal-doc-row__cell portal-doc-row__cell--meta" data-label="{{ __('Fecha') }}">
        <flux:badge variant="outline" size="sm" class="portal-doc-row__ext">{{ $document['extension'] }}</flux:badge>
        <span class="portal-doc-row__value portal-doc-row__value--date">{{ $document['uploaded_at_label'] ?: '—' }}</span>
    </div>

    <div class="portal-doc-row__action" data-label="{{ __('Acción') }}">
        @if (($document['exists'] ?? false) || (filled($document['source'] ?? null) && (int) ($document['source_id'] ?? 0) > 0 && filled($document['file_path'] ?? null)))
            <flux:button
                size="sm"
                variant="primary"
                icon="arrow-down-tray"
                class="portal-glass-button"
                :href="route('cases.documents.download', ['source' => $document['source'], 'id' => $document['source_id']])"
            >
                {{ __('Descargar') }}
            </flux:button>
        @else
            <flux:badge color="zinc" size="sm">{{ __('No disponible') }}</flux:badge>
        @endif
    </div>
</article>
