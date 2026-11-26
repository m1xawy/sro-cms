<div class="offcanvas-md offcanvas-end bg-body-tertiary" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarMenuLabel">{{ config('global.site_name') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-md-flex flex-column p-0 pt-lg-3 overflow-y-auto">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin') ? 'active' : '' }}" aria-current="page" href="{{ route('admin') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#house-fill"/></svg>
                    {{ __('Dashboard') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.worldmap') ? 'active' : '' }}" href="{{ route('admin.worldmap') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#house-fill"></use></svg>
                    {{ __('World Map') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.users.index') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#people"/></svg>
                    {{ __('Users') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.characters.index') ? 'active' : '' }}" href="{{ route('admin.characters.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#people"/></svg>
                    {{ __('Characters') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.news.index') ? 'active' : '' }}" href="{{ route('admin.news.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark"/></svg>
                    {{ __('News') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.download.index') ? 'active' : '' }}" href="{{ route('admin.download.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark"/></svg>
                    {{ __('Download') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.pages.index') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark"/></svg>
                    {{ __('Pages') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.vouchers.index') ? 'active' : '' }}" href="{{ route('admin.vouchers.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Vouchers') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.tickets.index') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Tickets') }}
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-body-secondary text-uppercase">
            <span>Logs</span>
            <!--
            <a class="link-secondary" href="#" aria-label="Add a new report">
                <svg class="bi" aria-hidden="true"><use xlink:href="#plus-circle"></use></svg>
            </a>
            -->
        </h6>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.logs.donate') ? 'active' : '' }}" href="{{ route('admin.logs.donate') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Donate Logs') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.logs.referral') ? 'active' : '' }}" href="{{ route('admin.logs.referral') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Referral Logs') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.logs.vote') ? 'active' : '' }}" href="{{ route('admin.logs.vote') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Vote Logs') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.logs.smc') ? 'active' : '' }}" href="{{ route('admin.logs.smc') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('SMC Logs') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.logs.error') ? 'active' : '' }}" href="{{ route('admin.logs.error') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#file-earmark-text"></use></svg>
                    {{ __('Error Logs') }}
                </a>
            </li>
        </ul>

        <hr class="my-3">
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-1 mb-1 text-body-secondary text-uppercase">
            <span>Settings</span>
        </h6>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}" href="{{ route('admin.settings.general') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#gear-wide-connected"/></svg>
                    {{ __('General') }}
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.settings.widgets') ? 'active' : '' }}" href="{{ route('admin.settings.widgets') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#gear-wide-connected"/></svg>
                    {{ __('Widgets') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.settings.donate') ? 'active' : '' }}" href="{{ route('admin.settings.donate') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#gear-wide-connected"/></svg>
                    {{ __('Donate') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.settings.ranking') ? 'active' : '' }}" href="{{ route('admin.settings.ranking') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#gear-wide-connected"/></svg>
                    {{ __('Ranking') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs('admin.settings.webapps') ? 'active' : '' }}" href="{{ route('admin.settings.webapps') }}">
                    <svg class="bi" aria-hidden="true"><use xlink:href="#gear-wide-connected"/></svg>
                    {{ __('Web Apps') }}
                </a>
            </li>
        </ul>
    </div>
</div>
