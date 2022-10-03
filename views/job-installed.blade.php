@component('email.indicator', ['status' => 'success'])
	Your application is installed.
@endcomponent

@component('mail::message')
{{-- Body --}}
# Howdy!

{{ $appname }} has been successfully installed on {{ $uri }}!

## Admin panel
You can access the panel at [{{$proto}}{{$uri}}{{\LithiumHosting\WebApps\Whmcs\Handler::ADMIN_PATH}}]({{$proto}}{{$uri}}{{\LithiumHosting\WebApps\Whmcs\Handler::ADMIN_PATH}}) using the following information:

**Login**: <code>{{ $whmcs_username }}</code><br/>
**Password**: <code>{{ str_replace('@', '\\@', $whmcs_password) }}</code>

---

Security is important with any application, so extra steps are taken to reduce
the risk of hackers. By default **Maximum** Fortification is enabled. This will
work for most people, but if you run into any problems change Fortification to
**Minimum**.  If you want to run the {{ $appname }} integrated update/upgrade
process, you will need to switch fortification to "Write Mode" for the duration
of the upgrade or the upgrade will fail.

Here's how to do it:

1. Visit **Web** > **Web Apps** in {{PANEL_BRAND}}
2. Select {{ $appname }} installed under **{{$uri}}**
3. Select **Fortification (Fortify MIN)** or **Fortification (Web App Write Mode)** under _Actions_ depending on your needs.

You can learn more about [Fortification technology]({{MISC_KB_BASE}}/control-panel/understanding-fortification/) within the [knowledgebase]({{MISC_KB_BASE}}).

@include('email.webapps.common-footer')
@endcomponent