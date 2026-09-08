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
            // What the light design shows: an id, 0 for the word mark, and
            // -- when there is no entry at all -- whatever the dark design
            // shows. The two zeroes are not the same thing, hence the flag.
            'lightId'      => (int) $this->settings->get(Settings::LOGO_ID_LIGHT, '0'),
            'lightFollows' => $this->settings->get(Settings::LOGO_ID_LIGHT) === null,
            'design'       => $design,
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
            // Live in the design the page was showing, and the message says
            // which one that was.
            $light = $request->post('scheme') === Theme::LIGHT;
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
     * One logo -- or the word mark -- goes live for the design the form
     * names in `scheme`. Id 0 is the word mark, in either design; the two
     * designs are set independently, so putting a logo up in the dark one
     * leaves a light one that was set to the word mark exactly there.
     * Following the dark design again is its own action, below.
     */
    public function logoActivate(Request $request): Response
    {
        $id    = $request->routeInt('id');
        $light = $request->post('scheme') === Theme::LIGHT;
        if ($id > 0 && $this->uploads->info($id) === null) {
            $this->flash('error', t('This logo was not found.'));

            return $this->redirectTo($this->back($this->url('logos')));
        }

        $this->settings->set(
            $light ? Settings::LOGO_ID_LIGHT : Settings::LOGO_ID,
            (string) $id,
        );
        $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
        $this->flash('ok', match (true) {
            $id > 0 && $light  => t('The light design shows this logo now.'),
            $id > 0            => t('The dark design shows this logo now.'),
            $light             => t('The light design shows the word mark now.'),
            default            => t('The dark design shows the word mark now.'),
        });

        return $this->redirectTo($this->back($this->url('logos')));
    }

    /**
     * The light design follows the dark one again: its entry goes away, so
     * whatever the dark design shows -- now and later -- is what it shows.
     */
    public function logoLightInherit(Request $request): Response
    {
        $this->settings->delete(Settings::LOGO_ID_LIGHT);
        $this->settings->increment(Ui::REVISION_KEY); // the header changed for everyone
        $this->flash('ok', t('The light design shows the same as the dark one again.'));

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
            //
            // What was kept from a failed or partial save lies *over* the
            // stored values, field by field, rather than replacing the lot:
            // "Save as my palette" hands back the fourteen colours and nothing
            // else, and the numbers below them have to keep standing. They
            // are required fields, so blanking them would leave a form that
            // cannot be submitted at all.
            'values' => ($kept['values'] ?? [])
                + Colors::fields($this->settings, true)
                + Colors::fields($this->settings, false)
                + ['theme' => $this->theme->fallback()]
                + array_map('strval', $this->ui->all()),
            'errors' => $kept['errors'] ?? [],
            // The admins' own palette, offered above the built-in ones.
            'ownPalette' => Colors::own($this->settings),
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

    /**
     * Keep what stands in the colour fields as the admins' own palette. The
     * same form as saveUi(), reached through a button with an address of its
     * own, so nothing is applied here -- the palette is offered at the top of
     * the preset row and applied when it is clicked and saved. What was typed
     * comes back into the fields either way, so a failed check does not lose
     * it, and the interface itself does not change: no revision is raised.
     */
    public function saveUiPalette(Request $request): Response
    {
        $input = [];
        foreach (Colors::AREAS as $area) {
            foreach ([true, false] as $dark) {
                $field         = Colors::field($area, $dark);
                $input[$field] = $request->post($field);
            }
        }

        $dark   = Colors::validate($input, true);
        $light  = Colors::validate($input, false);
        $errors = $dark['errors'] + $light['errors'];
        if ($errors !== []) {
            $this->support->forms->remember($input, $errors);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('ui');
        }

        Colors::saveOwn($this->settings, $dark['values'] + $light['values']);
        $this->support->forms->remember($dark['values'] + $light['values'], []);
        $this->flash('ok', t('The colours have been kept as your own palette.'));

        return $this->redirect('ui');
    }

    /**
     * Drop the admins' own palette. The colours in the fields and the ones
     * the site is drawn in are untouched: only the entry at the head of the
     * preset row goes.
     */
    public function deleteUiPalette(Request $request): Response
    {
        if (Colors::own($this->settings) === null) {
            $this->flash('error', t('There is no palette of your own to delete.'));

            return $this->redirect('ui');
        }

        $this->settings->delete(Colors::OWN_KEY);
        $this->flash('ok', t('Your own palette has been deleted.'));

        return $this->redirect('ui');
    }

    /**
     * The colour block the fourteen fields would produce, as CSS, so the
     * Interface page can show a change before it is saved. Nothing is
     * stored, and nothing is read from the settings: the answer is built
     * from what was typed alone.
     *
     * The block carries every scheme's own selector, exactly as the saved
     * one does, so the browser applies the one the page is drawn in and the
     * switch in the header keeps working. An area left empty falls back to
     * the built-in colour, or the preview would drop a colour the saved
     * block still shows. A value that is not a colour is left out, which is
     * what a half-typed "#1e4" is.
     */
    public function previewUi(Request $request): Response
    {
        $css = '';
        foreach ([true, false] as $dark) {
            $colors = [];
            foreach (Colors::AREAS as $area) {
                $typed = Colors::parse((string) $request->post(Colors::field($area, $dark)));
                $colors[$area] = $typed === null ? Colors::defaults($dark)[$area] : Colors::hex($typed);
            }
            $css .= Colors::css($colors, $dark);
            // The light values once more for a visitor following their device.
            if (!$dark) {
                $system = Colors::css($colors, false, Theme::SYSTEM);
                $css .= $system === '' ? '' : '@media (prefers-color-scheme: light){' . $system . '}';
            }
        }

        return new Response($css, 200, [
            'Content-Type'  => 'text/css; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
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
