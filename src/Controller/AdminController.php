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
use Songwunsch\Theme;
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
        private readonly Theme $theme,
    ) {
        parent::__construct($support);
    }

    /** /admin leads to the users -- the entry an admin needs most. */
    public function home(Request $request): Response
    {
        return $this->redirect('users');
    }

    /**
     * Every uploaded logo, and the one live in one design. The page shows a
     * design at a time -- `?design=light` for the other -- so a row carries
     * one action and not two of them (see docs/logo.md).
     */
    public function logos(Request $request): View
    {
        $design  = $request->query('design') === Theme::LIGHT ? Theme::LIGHT : Theme::DARK;
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
            // The light design's own logo, or 0 while it follows the one
            // above -- no entry at all is what "follows" looks like.
            'lightId'  => (int) $this->settings->get(Settings::LOGO_ID_LIGHT, '0'),
            'design'   => $design,
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

            return $this->redirectTo($this->back($this->url('logos')));
        }

        $newId = $this->uploads->add(Uploads::LOGO, $check);
        if ($request->post('activate') === '1') {
            // Live in the design the page was showing. The light slot only
            // means something once a logo is live at all, so the very first
            // upload goes to the dark design whichever page it came from --
            // and the message says which design got it.
            $light = $request->post('scheme') === Theme::LIGHT
                && (int) $this->settings->get(Settings::LOGO_ID, '0') > 0;
            $this->settings->set(
                $light ? Settings::LOGO_ID_LIGHT : Settings::LOGO_ID,
                (string) $newId,
            );
            $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
            $this->flash('ok', $light
                ? t('The logo has been uploaded and is live in the light design.')
                : t('The logo has been uploaded and is live in the dark design.'));
        } else {
            $this->flash('ok', t('The logo has been uploaded – switch it live below when you want it shown.'));
        }

        return $this->redirectTo($this->back($this->url('logos')));
    }

    /**
     * One logo goes live -- for the dark design or the light one, whichever
     * the form names in `scheme`. Id 0 means the neutral choice of that
     * slot: the word mark for the dark design, and for the light one "the
     * same as the dark design", which is no entry at all.
     */
    public function logoActivate(Request $request): Response
    {
        $id    = $request->routeInt('id');
        $light = $request->post('scheme') === Theme::LIGHT;
        if ($id > 0 && $this->uploads->info($id) === null) {
            $this->flash('error', t('This logo was not found.'));

            return $this->redirectTo($this->back($this->url('logos')));
        }

        if (!$light) {
            $this->settings->set(Settings::LOGO_ID, (string) $id);
            if ($id === 0) {
                // No logo at all: a logo kept for the light design alone
                // would be a setting with nothing behind it.
                $this->settings->delete(Settings::LOGO_ID_LIGHT);
            }
            $message = $id > 0
                ? t('The dark design shows this logo now.')
                : t('The header shows the word mark again.');
        } elseif ($id > 0) {
            $this->settings->set(Settings::LOGO_ID_LIGHT, (string) $id);
            $message = t('The light design shows this logo now.');
        } else {
            $this->settings->delete(Settings::LOGO_ID_LIGHT);
            $message = t('The light design shows the same logo as the dark one again.');
        }

        $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
        $this->flash('ok', $message);

        return $this->redirectTo($this->back($this->url('logos')));
    }

    public function logoDelete(Request $request): Response
    {
        $id = $request->routeInt('id');
        if (!$this->uploads->delete($id)) {
            $this->flash('error', t('This logo was not found.'));

            return $this->redirectTo($this->back($this->url('logos')));
        }

        // Whichever design was showing it falls back by itself: the dark
        // one to the word mark, the light one to the dark one's logo.
        $wasLive = false;
        if ((int) $this->settings->get(Settings::LOGO_ID, '0') === $id) {
            $this->settings->delete(Settings::LOGO_ID); // no logo: no entry, the reads default to 0
            $wasLive = true;
        }
        if ((int) $this->settings->get(Settings::LOGO_ID_LIGHT, '0') === $id) {
            $this->settings->delete(Settings::LOGO_ID_LIGHT);
            $wasLive = true;
        }
        if ($wasLive) {
            $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
            $this->flash('ok', t('The logo has been deleted – the header falls back to what it showed before.'));
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
            // Two colour sets -- one per scheme, keyed by field name so they
            // cannot collide -- the default scheme, and the numbers.
            'values' => $kept['values'] ?? Colors::fields($this->settings, true)
                + Colors::fields($this->settings, false)
                + ['theme' => $this->theme->fallback()]
                + array_map('strval', $this->ui->all()),
            'errors' => $kept['errors'] ?? [],
        ]);
    }

    public function saveUi(Request $request): Response
    {
        $input = [];
        foreach (Colors::AREAS as $area) {
            foreach ([true, false] as $dark) {
                $field         = Colors::field($area, $dark);
                $input[$field] = $request->post($field);
            }
        }
        foreach (array_keys(Ui::FIELDS) as $name) {
            $input[$name] = $request->post($name);
        }
        $input['theme'] = $request->post('theme');

        $dark    = Colors::validate($input, true);
        $light   = Colors::validate($input, false);
        $numbers = $this->ui->validate($input);
        $errors  = $dark['errors'] + $light['errors'] + $numbers['errors'];

        if ($errors !== []) {
            $this->support->forms->remember($input, $errors);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('ui');
        }

        Colors::save($this->settings, $dark['values'], true);
        Colors::save($this->settings, $light['values'], false);
        // Which scheme a visitor without a choice of their own gets. An
        // unknown value keeps the current one -- the field is a select.
        $this->theme->saveFallback((string) $input['theme']);
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
