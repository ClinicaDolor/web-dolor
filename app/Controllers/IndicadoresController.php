<?php

namespace App\Controllers;

use App\Helpers\Sidebar;
use App\Middleware\AuthMiddleware;
use App\Models\ClinicaModel;
use App\Services\ClinicaIndicadoresService;
use Throwable;

final class IndicadoresController extends BaseController
{
    public function index(): void
    {
        $authMiddleware = new AuthMiddleware('clinica');
        $auth = $authMiddleware->authPermisos();
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $sidebarController->configureSidebar('DOCTOR', 'clinica-indicadores', $sidebar);
        $sidebar->setActivarItem('Indicadores');

        $rawYear = $_GET['year'] ?? 'all';
        $selectedYear = null;
        $filterError = null;
        if ($rawYear !== 'all' && $rawYear !== '') {
            if (!is_string($rawYear) || !preg_match('/^\d{4}$/', $rawYear)) {
                $filterError = 'El periodo seleccionado no es válido; se muestran todos los años.';
            } else {
                $selectedYear = (int) $rawYear;
            }
        }

        $availableYears = [];
        $dashboardSummary = null;
        $indicatorData = null;
        $dataError = null;

        try {
            $clinicaModel = new ClinicaModel();
            $dashboardSummary = $clinicaModel->getDashboardSummary($auth['id_clinica']);
            $service = new ClinicaIndicadoresService();
            $availableYears = $service->getAvailableYears((int) $auth['id_clinica']);

            if ($selectedYear !== null && !in_array($selectedYear, $availableYears, true)) {
                $filterError = 'El año seleccionado no tiene datos disponibles; se muestran todos los años.';
                $selectedYear = null;
            }

            $indicatorData = $service->getDashboardData(
                (int) $auth['id_clinica'],
                $selectedYear,
                $dashboardSummary
            );
        } catch (Throwable $exception) {
            error_log('D41 indicator read failure: ' . get_class($exception));
            $dataError = 'No fue posible cargar los indicadores en este momento.';
        }

        $this->view('/clinica/indicadores.php', [
            'title' => 'Centro de indicadores',
            'datos' => $auth,
            'sidebar' => $sidebar->render(),
            'available_years' => $availableYears,
            'selected_year' => $selectedYear,
            'filter_error' => $filterError,
            'dashboard_summary' => $dashboardSummary,
            'indicator_data' => $indicatorData,
            'data_error' => $dataError,
        ]);
    }
}
