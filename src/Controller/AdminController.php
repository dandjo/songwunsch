<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Colors;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Pagination;
use Songwunsch\Settings;
use Songwunsch\Template\View;
use Songwunsch\Ui;
use Songwunsch\Uploads;

/**
 * The Administration menu, apart from users, pages, the footer and the
 * languages, which have controllers of their own: the header logos, the
 * interface (colours, message duration, polling pace) and the limits on
 * wishing and suggesting.
 */
final class AdminController extends Controller
{
    public function __construct(
        Support $support,
        private readonly Settings $settings,
        private readonly Uploads $uploads,
        private readonly Ui $ui,
        private readonly Limits $limits,
    ) {
        parent::__construct($support);
    }

    /** /admin leads to the users -- the entry an admin needs most. */
    public function home(Request $request): Response
    {
        return $this->redirect('users');
    }

    /** Every uploaded logo, one of them live. */
    public function logos(Request $request): View
    {
        $perPage = $this->limits->get('per_page');
        $result  = Pagination::of(
            fn (int $page): array => $this->uploads->page(Uploads::LOGO, $page, $perPage),
            (int) $request->query('page', '1'),
            $perPage,
        );

        return $this->view('logos', t('Logos'), [
            'logos'    => $result['rows'],
            'total'    => $result['total'],
            'pageNo'   => $result['page'],
            'pages'    => $result['pages'],
            'activeId' => (int) $this->settings->get(Settings::LOGO_ID, '0'),
        ]);
    }

    /**
     * A new logo. Kept in the database (Uploads), and it may go live right
     * away.
     */
    public function logoUpload(Request $request): Response
    {
        $check = $this->uploads->check($request->file('logo'));
        if ($check['errors'] !== []) {
            $this->flash('error', implode(' ', $check['errors']));

            return $this->redirect('logos');
        }

        $newId = $this->uploads->add(Uploads::LOGO, $check);
        if ($request->post('activate') === '1') {
            $this->settings->set(Settings::LOGO_ID, (string) $newId);
            $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
            $this->flash('ok', t('The logo has been uploaded and is live in the header.'));
        } else {
            $this->flash('ok', t('The logo has been uploaded – switch it live below when you want it shown.'));
        }

        return $this->redirect('logos');
    }

    /** Exactly one logo is live -- or none: id 0 brings the word mark back. */
    public function logoActivate(Request $request): Response
    {
        $id = $request->routeInt('id');
        if ($id > 0 && $this->uploads->info($id) === null) {
            $this->flash('error', t('This logo was not found.'));

            return $this->redirectTo($this->back($this->url('logos')));
        }

        $this->settings->set(Settings::LOGO_ID, (string) $id);
        $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
        $this->flash('ok', $id > 0
            ? t('The header shows this logo now.')
            : t('The header shows the word mark again.'));

        return $this->redirectTo($this->back($this->url('logos')));
    }

    public function logoDelete(Request $request): Response
    {
        $id = $request->routeInt('id');
        if (!$this->uploads->delete($id)) {
            $this->flash('error', t('This logo was not found.'));

            return $this->redirectTo($this->back($this->url('logos')));
        }

        if ((int) $this->settings->get(Settings::LOGO_ID, '0') === $id) {
            $this->settings->delete(Settings::LOGO_ID); // no logo: no entry, the reads default to 0
            $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
            $this->flash('ok', t('The logo has been deleted – the header shows the word mark again.'));
        } else {
            $this->flash('ok', t('The logo has been deleted.'));
        }

        return $this->redirectTo($this->back($this->url('logos')));
    }

    /**
     * An uploaded logo as its own resource for <img src="/logo/<id>">.
     *
     * The bytes of an id never change, so the browser may keep them for
     * good; a browser that asks anyway (a hard reload) gets a 304 without
     * the bytes leaving the database. Response::file() drops the headers
     * session_start() queued to keep pages out of caches -- browsers honour
     * Pragma over Cache-Control and would otherwise fetch the picture again
     * on every screen.
     */
    public function logoFile(Request $request): Response
    {
        $id = $request->routeInt('id');
        if ($this->uploads->info($id) === null) {
            $this->notFound();
        }

        $etag = '"logo-' . $id . '"';
        if (str_contains($request->server('HTTP_IF_NONE_MATCH'), $etag)) {
            return Response::empty(304)
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
                ->withoutHeaders('Expires', 'Pragma', 'Set-Cookie');
        }

        $file = $this->uploads->load($id);
        if ($file === null) {
            $this->notFound();
        }

        return Response::file($file['data'], (string) $file['mime'])
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->withHeader('ETag', $etag);
    }

    /**
     * The interface: one hex colour per area of use (or nothing for the
     * built-in colour), the message duration and the polling intervals.
     * One form, two stores.
     */
    public function ui(Request $request): View
    {
        $kept = $this->support->forms->take();

        return $this->view('ui', t('Interface'), [
            'values' => $kept['values'] ?? Colors::load($this->settings) + array_map('strval', $this->ui->all()),
            'errors' => $kept['errors'] ?? [],
        ]);
    }

    public function saveUi(Request $request): Response
    {
        $input = [];
        foreach (Colors::AREAS as $area) {
            $input[$area] = $request->post($area);
        }
        foreach (array_keys(Ui::FIELDS) as $name) {
            $input[$name] = $request->post($name);
        }

        $colors  = Colors::validate($input);
        $numbers = $this->ui->validate($input);
        $errors  = $colors['errors'] + $numbers['errors'];

        if ($errors !== []) {
            $this->support->forms->remember($input, $errors);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('ui');
        }

        Colors::save($this->settings, $colors['values']);
        $this->ui->save($numbers['values']);
        // Pages that are open take on the new colours, the new message
        // duration and the new polling pace at their next poll -- they carry
        // this counter in their head token.
        $this->settings->increment(Ui::REVISION_KEY);
        $this->flash('ok', t('The interface settings have been saved.'));

        return $this->redirect('ui');
    }

    /** The limits on wishing and suggesting, for every room. */
    public function limits(Request $request): View
    {
        $kept = $this->support->forms->take();

        return $this->view('limits', t('Limits'), [
            'values' => $kept['values'] ?? array_map('strval', $this->limits->all()),
            'errors' => $kept['errors'] ?? [],
        ]);
    }

    public function saveLimits(Request $request): Response
    {
        $input = [];
        foreach (array_keys(Limits::FIELDS) as $name) {
            $input[$name] = $request->post($name);
        }

        $checked = $this->limits->validate($input);
        if ($checked['errors'] !== []) {
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('limits');
        }

        $this->limits->save($checked['values']);
        $this->flash('ok', t('The limits have been saved.'));

        return $this->redirect('limits');
    }
}
