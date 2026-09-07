<?php

declare(strict_types=1);

final class FriendsController
{
    private FriendService $friends;
    private AuditService $audit;

    public function __construct()
    {
        $this->friends = new FriendService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $list = $this->friends->listActive($userId);
        $suggested = $this->friends->nextSuggestion($userId);
        $stats = $this->friends->stats($userId);

        view('friends/index', [
            'title' => 'Amigos/as',
            'currentNav' => 'friends',
            'friends' => $list,
            'suggested' => $suggested,
            'stats' => $stats,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $id = $this->friends->create($userId, (string) input('name', ''));
            $this->audit->log($userId, 'friend.create', 'friend', $id);
            respond_saved('Amigo/a agregado.', '/friends');
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo agregar.',
                '/friends'
            );
        }
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $this->friends->update($userId, (int) $id, (string) input('name', ''));
            $this->audit->log($userId, 'friend.update', 'friend', (int) $id);
            respond_saved('Nombre actualizado.', '/friends');
        } catch (Throwable $e) {
            respond_error(
                $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo actualizar.',
                '/friends'
            );
        }
    }

    public function destroy(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        try {
            $this->friends->delete($userId, (int) $id);
            $this->audit->log($userId, 'friend.delete', 'friend', (int) $id);
            respond_saved('Quitado de la lista.', '/friends');
        } catch (Throwable $e) {
            respond_error('No se pudo quitar.', '/friends');
        }
    }
}
