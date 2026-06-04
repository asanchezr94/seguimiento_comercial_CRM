@extends('layout')

@section('content')
<h2>Visitas</h2>

<div class="inline-filters visitas-toolbar">
    <form method="get" action="{{ route('visitas.index') }}" id="filtro-visitas" class="inline-filters visitas-toolbar-form">
        <div class="field">
            <label>Mes</label>
            <select name="mes" onchange="document.getElementById('filtro-visitas').submit()">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
            </select>
        </div>
        <div class="field">
            <label>Ano</label>
            <select name="anio" onchange="document.getElementById('filtro-visitas').submit()">
                @for($year = 2026; $year <= 2036; $year++)
                    <option value="{{ $year }}" @selected((int) $anio === $year)>{{ $year }}</option>
                @endfor
            </select>
        </div>
    </form>
    <div class="visitas-toolbar-actions">
        <a class="btn-link visitas-hoy-btn" href="{{ route('visitas.index') }}">Hoy</a>
        <button type="button" id="btn-open-visita">Programar visita</button>
    </div>
</div>

<p>Periodo seleccionado: <strong>{{ $inicioMes->locale('es')->translatedFormat('F Y') }}</strong></p>

<style>
    .visitas-toolbar {
        align-items: flex-end;
        justify-content: space-between;
        gap: 10px;
    }
    .visitas-toolbar-form {
        align-items: flex-end;
        margin-bottom: 0;
    }
    .visitas-toolbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 0;
    }
    .visitas-hoy-btn {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(150px, 1fr));
        gap: 8px;
        margin-top: 12px;
    }
    .calendar-day-name {
        background: var(--primary-table);
        color: #063f5d;
        border: 1px solid #a8cbe1;
        border-radius: 8px;
        padding: 8px;
        font-weight: 800;
        text-align: center;
    }
    .calendar-day {
        min-height: 150px;
        border: 1px solid #b5d1e4;
        border-radius: 8px;
        background: #fff;
        padding: 8px;
    }
    .calendar-day.out-month {
        background: #f5f9fc;
        color: #7890a5;
    }
    .calendar-date {
        font-weight: 800;
        color: #073f61;
        margin-bottom: 6px;
    }
    .visit-card {
        border: 1px solid #b6d1e5;
        border-left: 4px solid var(--primary);
        border-radius: 8px;
        padding: 7px;
        margin-bottom: 6px;
        background: #f8fcff;
        white-space: normal;
    }
    .visit-card.realizada { border-left-color: #0f7a34; }
    .visit-card.cancelada { border-left-color: #b42318; }
    .visit-meta {
        color: var(--muted);
        font-size: 0.82rem;
        margin: 2px 0;
    }
    .visit-actions {
        margin-top: 6px;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    @media (max-width: 980px) {
        .calendar-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
        .calendar-day-name { display: none; }
    }
</style>

<div class="calendar-grid">
    @foreach(['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $dia)
        <div class="calendar-day-name">{{ $dia }}</div>
    @endforeach

    @php($cursor = $inicioCalendario->copy())
    @while($cursor <= $finCalendario)
        @php($key = $cursor->format('Y-m-d'))
        <div class="calendar-day @if($cursor->month !== $mes) out-month @endif">
            <div class="calendar-date">{{ $cursor->format('d/m') }}</div>
            @foreach(($visitasPorDia[$key] ?? collect()) as $visita)
                <div class="visit-card {{ $visita->estado }}">
                    <strong>
                        {{ $visita->programada_at->format('H:i') }}
                        @if($visita->finaliza_at)
                            - {{ $visita->finaliza_at->format('H:i') }}
                        @endif
                        | {{ $visita->cliente_nombre }}
                    </strong>
                    <div class="visit-meta">{{ $visita->asesor?->name ?? 'N/A' }}</div>
                    @if($visita->direccion)
                        <div class="visit-meta">{{ $visita->direccion }}</div>
                    @endif
                    <div class="visit-meta">Estado: {{ ucfirst($visita->estado) }}</div>
                    @if($visita->resultado)
                        <div class="visit-meta">{{ $visita->resultado }}</div>
                    @endif
                    <div class="visit-actions">
                        <a class="btn-link" href="{{ route('visitas.show', $visita->id) }}">Ver</a>
                    </div>
                    @if($visita->estado === 'programada' && (auth()->user()?->role === 'supervisor' || $visita->user_id === auth()->id()))
                        <div class="visit-actions">
                            <button type="button" class="btn-registrar-visita" data-action="{{ route('visitas.registrar', $visita->id) }}" data-cliente="{{ $visita->cliente_nombre }}">Registrar</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @php($cursor->addDay())
    @endwhile
</div>

@push('page-modals')
<div id="visita-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Programar visita</h3>
            <button type="button" class="modal-close" id="btn-close-visita">Cerrar</button>
        </div>
        <form method="post" action="{{ route('visitas.store') }}">
            @csrf
            @if(auth()->user()?->role === 'supervisor')
                <label>Asesor</label>
                <select name="user_id">
                    <option value="">Yo mismo</option>
                    @foreach($asesores as $asesor)
                        <option value="{{ $asesor->id }}">{{ $asesor->name }}</option>
                    @endforeach
                </select>
            @endif
            <label>Cliente</label>
            <input type="text" name="cliente_nombre" required>
            <label>Telefono</label>
            <input type="text" name="telefono">
            <label>Direccion</label>
            <input type="text" name="direccion">
            <label>Titulo / motivo</label>
            <input type="text" name="titulo" placeholder="Ej: Visita comercial">
            <label>Fecha y hora inicio</label>
            <input type="datetime-local" name="programada_at" required>
            <label>Fecha y hora fin</label>
            <input type="datetime-local" name="finaliza_at" required>
            <button type="submit">Guardar visita</button>
        </form>
    </div>
</div>

<div id="registrar-visita-modal" class="modal-backdrop modal-backdrop-strong" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3 style="margin:0;">Registrar visita</h3>
            <button type="button" class="modal-close" id="btn-close-registrar-visita">Cerrar</button>
        </div>
        <form method="post" id="form-registrar-visita" action="">
            @csrf
            <p id="registrar-visita-cliente" style="margin-top:0;"></p>
            <label>Estado</label>
            <select name="estado" required>
                <option value="realizada">Realizada</option>
                <option value="cancelada">Cancelada</option>
            </select>
            <label>Resultado de la visita</label>
            <textarea name="resultado" required placeholder="Describe que se hizo en la visita..."></textarea>
            <button type="submit">Guardar resultado</button>
        </form>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
    (function () {
        const visitaModal = document.getElementById('visita-modal');
        const openVisita = document.getElementById('btn-open-visita');
        const closeVisita = document.getElementById('btn-close-visita');
        const registroModal = document.getElementById('registrar-visita-modal');
        const closeRegistro = document.getElementById('btn-close-registrar-visita');
        const formRegistro = document.getElementById('form-registrar-visita');
        const clienteRegistro = document.getElementById('registrar-visita-cliente');

        const open = (modal) => {
            modal?.classList.add('open');
            modal?.setAttribute('aria-hidden', 'false');
        };
        const close = (modal) => {
            modal?.classList.remove('open');
            modal?.setAttribute('aria-hidden', 'true');
        };

        openVisita?.addEventListener('click', () => open(visitaModal));
        closeVisita?.addEventListener('click', () => close(visitaModal));
        closeRegistro?.addEventListener('click', () => close(registroModal));

        document.querySelectorAll('.btn-registrar-visita').forEach((btn) => {
            btn.addEventListener('click', () => {
                formRegistro?.setAttribute('action', btn.dataset.action || '');
                if (clienteRegistro) clienteRegistro.textContent = btn.dataset.cliente || '';
                open(registroModal);
            });
        });

        [visitaModal, registroModal].forEach((modal) => {
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) close(modal);
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                close(visitaModal);
                close(registroModal);
            }
        });

        setTimeout(() => {
            if (!document.querySelector('.modal-backdrop.open')) {
                window.location.reload();
            }
        }, 60000);
    })();
</script>
@endpush
@endsection
