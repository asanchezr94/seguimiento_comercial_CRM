@extends('layout')

@section('content')
<h2>Notificaciones</h2>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Titulo</th>
            <th>Mensaje</th>
            <th>Estado</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($notifications as $n)
            <tr>
                <td>{{ $n->created_at?->format('Y-m-d H:i') }}</td>
                <td>{{ $n->title }}</td>
                <td>{{ $n->message }}</td>
                <td>{{ $n->read_at ? 'Leida' : 'Pendiente' }}</td>
                <td>
                    @if(!$n->read_at)
                        <form method="post" action="{{ route('notifications.read', $n->id) }}" class="inline">
                            @csrf
                            <button type="submit">Marcar leida</button>
                        </form>
                    @else
                        N/A
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No tienes notificaciones.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $notifications->links() }}
@endsection

