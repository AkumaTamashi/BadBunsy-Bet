// ============================================================
// Bad Bunsy Bet - JavaScript Principal
// ============================================================

// ---- Betslip State ----
let betslip = [];

const SITE_URL =
    document.querySelector('meta[name="site-url"]')?.content || '';

// ============================================================
// NAVBAR
// ============================================================

function toggleMobileMenu() {
    document.getElementById('navMenu')?.classList.toggle('open');
}

function toggleUserMenu() {
    document.getElementById('userDropdown')?.classList.toggle('show');
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.nav-user-menu')) {
        document
            .getElementById('userDropdown')
            ?.classList.remove('show');
    }
});

// ============================================================
// FLASH MESSAGES
// ============================================================

setTimeout(() => {
    const flash = document.getElementById('flashMsg');

    if (flash) {
        flash.style.animation = 'slideIn 0.3s ease reverse';

        setTimeout(() => {
            flash.remove();
        }, 300);
    }
}, 4000);

// ============================================================
// BETSLIP
// ============================================================

function addToBetslip(
    eventoId,
    partidoId,
    descripcion,
    cuota,
    jugadorRelacionado
) {
    const jugadorAsociado =
        document.querySelector(
            'meta[name="jugador-asociado"]'
        )?.content || '';

    // Restricción de jugador
    if (
        jugadorRelacionado !== 'ninguno' &&
        jugadorRelacionado.toLowerCase() ===
            jugadorAsociado.toLowerCase()
    ) {
        showAlert(
            'error',
            `No puedes apostar en eventos relacionados con ${jugadorRelacionado}`
        );

        return;
    }

    // Evitar duplicados
    if (betslip.some((b) => b.eventoId == eventoId)) {
        removeFromBetslip(eventoId);
        return;
    }

    betslip.push({
        eventoId,
        partidoId,
        descripcion,
        cuota: parseFloat(cuota),
        jugadorRelacionado
    });

    updateBetslipUI();

    // Activar botón
    document
        .querySelectorAll(`[data-evento="${eventoId}"]`)
        .forEach((btn) => btn.classList.add('selected'));
}

function removeFromBetslip(eventoId) {
    betslip = betslip.filter((b) => b.eventoId != eventoId);

    document
        .querySelectorAll(`[data-evento="${eventoId}"]`)
        .forEach((btn) => btn.classList.remove('selected'));

    updateBetslipUI();
}

function clearBetslip() {
    betslip = [];

    document
        .querySelectorAll('.odd-btn.selected')
        .forEach((btn) => btn.classList.remove('selected'));

    updateBetslipUI();
}

function updateBetslipUI() {
    const container =
        document.getElementById('betslipContainer');

    if (!container) return;

    // Ocultar si no hay apuestas
    if (betslip.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';

    const itemsEl =
        document.getElementById('betslipItems');

    const countEl =
        document.getElementById('betslipCount');

    const cuotaEl =
        document.getElementById('betslipCuota');

    const gananciaEl =
        document.getElementById('betslipGanancia');

    const tipoEl =
        document.getElementById('betslipTipo');

    // Tipo
    if (countEl) {
        countEl.textContent = betslip.length;
    }

    if (tipoEl) {
        tipoEl.textContent =
            betslip.length === 1
                ? 'Simple'
                : 'Combinada';
    }

    // Cuota total
    const cuotaTotal = betslip.reduce(
        (acc, b) => acc * b.cuota,
        1
    );

    if (cuotaEl) {
        cuotaEl.textContent = cuotaTotal.toFixed(2);
    }

    // Ganancia
    const monto = parseFloat(
        document.getElementById('betMonto')?.value || 0
    );

    const ganancia =
        monto > 0
            ? Math.floor(monto * cuotaTotal)
            : 0;

    if (gananciaEl) {
        gananciaEl.textContent =
            monto > 0
                ? formatNumber(ganancia) + ' BB'
                : '—';
    }

    // Renderizar eventos
    if (itemsEl) {
        itemsEl.innerHTML = betslip
            .map(
                (b) => `
            <div class="betslip-item">

                <div>
                    <div class="betslip-event">
                        ${b.descripcion}
                    </div>

                    <div class="betslip-odd">
                        ${b.cuota.toFixed(2)}
                    </div>
                </div>

                <button
                    class="betslip-remove"
                    onclick="removeFromBetslip(${b.eventoId})"
                >
                    <i class="fas fa-times"></i>
                </button>

            </div>
        `
            )
            .join('');
    }
}

function updateGanancia() {
    if (betslip.length === 0) return;

    const monto = parseFloat(
        document.getElementById('betMonto')?.value || 0
    );

    const cuotaTotal = betslip.reduce(
        (acc, b) => acc * b.cuota,
        1
    );

    const gananciaEl =
        document.getElementById('betslipGanancia');

    if (!gananciaEl) return;

    if (monto > 0) {
        gananciaEl.textContent =
            formatNumber(
                Math.floor(monto * cuotaTotal)
            ) + ' BB';
    } else {
        gananciaEl.textContent = '—';
    }
}

function setQuickAmount(amount) {
    const input =
        document.getElementById('betMonto');

    if (!input) return;

    input.value = amount;

    updateGanancia();
}

// ============================================================
// MOSTRAR MODAL
// ============================================================

function submitBet() {
    if (betslip.length === 0) {
        showAlert(
            'error',
            'Agrega al menos un evento.'
        );

        return;
    }

    const monto = parseFloat(
        document.getElementById('betMonto')?.value || 0
    );

    if (monto <= 0) {
        showAlert(
            'error',
            'Ingresa un monto válido.'
        );

        return;
    }

    if (monto < 1000) {
        showAlert(
            'error',
            'El monto mínimo es 1000 BB.'
        );

        return;
    }

    const cuotaTotal = betslip.reduce(
        (acc, b) => acc * b.cuota,
        1
    );

    const ganancia =
        Math.floor(monto * cuotaTotal);

    const modal =
        document.getElementById('confirmModal');

    if (!modal) return;

    document.getElementById(
        'confirmMonto'
    ).textContent =
        formatNumber(monto) + ' BB';

    document.getElementById(
        'confirmCuota'
    ).textContent =
        cuotaTotal.toFixed(2);

    document.getElementById(
        'confirmGanancia'
    ).textContent =
        formatNumber(ganancia) + ' BB';

    document.getElementById(
        'confirmTipo'
    ).textContent =
        betslip.length === 1
            ? 'Simple'
            : `Combinada (${betslip.length} eventos)`;

    modal.classList.add('show');
}

// ============================================================
// CONFIRMAR APUESTA
// ============================================================

function confirmarApuesta() {
    if (betslip.length === 0) {
        showAlert(
            'error',
            'No hay eventos seleccionados.'
        );

        return;
    }

    const monto = parseFloat(
        document.getElementById('betMonto')?.value || 0
    );

    const cuotaTotal = betslip.reduce(
        (acc, b) => acc * b.cuota,
        1
    );

    const ganancia =
        Math.floor(monto * cuotaTotal);

    const tipo =
        betslip.length === 1
            ? 'simple'
            : 'combinada';

    const partidoId = betslip[0].partidoId;

    const eventos = betslip.map(
        (b) => b.eventoId
    );

    const data = new FormData();

    data.append('monto', monto);

    data.append(
        'cuota_total',
        cuotaTotal.toFixed(4)
    );

    data.append(
        'posible_ganancia',
        ganancia
    );

    data.append('tipo', tipo);

    data.append('partido_id', partidoId);

    data.append(
        'eventos',
        JSON.stringify(eventos)
    );

    fetch(
        SITE_URL + '/apuestas/realizar.php',
        {
            method: 'POST',
            body: data
        }
    )
        .then(async (response) => {
            const text =
                await response.text();

            console.log(
                'RESPUESTA RAW:',
                text
            );

            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(
                    'La respuesta no es JSON válido'
                );
            }
        })

        .then((res) => {
            console.log('JSON:', res);

            closeModal('confirmModal');

            if (res.success) {
                showAlert(
                    'success',
                    res.message
                );

                clearBetslip();

                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert(
                    'error',
                    res.message ||
                        'Error desconocido'
                );
            }
        })

        .catch((error) => {
            console.error(error);

            showAlert(
                'error',
                error.message
            );
        });
}

// ============================================================
// MODAL
// ============================================================

function closeModal(id) {
    document
        .getElementById(id)
        ?.classList.remove('show');
}

document.addEventListener('click', function (e) {
    if (
        e.target.classList.contains(
            'modal-overlay'
        )
    ) {
        e.target.classList.remove('show');
    }
});

// ============================================================
// ALERTAS
// ============================================================

function showAlert(type, message) {
    const existing =
        document.querySelector(
            '.dynamic-alert'
        );

    existing?.remove();

    const alert =
        document.createElement('div');

    alert.className = `flash-message flash-${type} dynamic-alert`;

    alert.innerHTML = `
        <i class="fas ${
            type === 'success'
                ? 'fa-check-circle'
                : 'fa-exclamation-circle'
        }"></i>

        ${message}

        <button onclick="this.parentElement.remove()">
            ×
        </button>
    `;

    document.body.appendChild(alert);

    setTimeout(() => {
        alert.style.animation =
            'slideIn 0.3s ease reverse';

        setTimeout(() => {
            alert.remove();
        }, 300);
    }, 4000);
}

// ============================================================
// UTILIDADES
// ============================================================

function formatNumber(n) {
    return parseFloat(n).toLocaleString(
        'es-CO',
        {
            maximumFractionDigits: 0
        }
    );
}

// ============================================================
// ADMIN
// ============================================================

function setResultado(eventoId, resultado) {
    document
        .querySelectorAll(
            `[data-evento-id="${eventoId}"]`
        )
        .forEach((btn) => {
            btn.classList.remove(
                'btn-primary',
                'btn-danger',
                'btn-outline'
            );

            btn.classList.add('btn-outline');
        });

    const selected =
        document.querySelector(
            `[data-evento-id="${eventoId}"][data-resultado="${resultado}"]`
        );

    if (selected) {
        selected.classList.remove(
            'btn-outline'
        );

        selected.classList.add(
            resultado === 'ganada'
                ? 'btn-primary'
                : 'btn-danger'
        );

        document.getElementById(
            `resultado_${eventoId}`
        ).value = resultado;
    }
}

function updateOddPreview(input) {
    const odd = parseFloat(
        input.value || 1
    );

    const preview =
        input.parentElement.querySelector(
            '.odd-preview'
        );

    if (preview) {
        preview.textContent =
            `Cuota: ${odd.toFixed(2)}x`;
    }
}

// ============================================================
// INIT
// ============================================================

document.addEventListener(
    'DOMContentLoaded',
    function () {
        document
            .getElementById('betMonto')
            ?.addEventListener(
                'input',
                updateGanancia
            );
    }
);