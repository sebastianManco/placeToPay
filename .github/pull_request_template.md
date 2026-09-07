## 📋 Descripción del cambio
<!-- Describe de forma clara y concisa los cambios introducidos en este PR -->

## 🌿 Tipo de cambio
- [ ] **Feature** (`feature/*` hacia `develop`): Nueva funcionalidad o mejora en el entorno
- [ ] **Fix / Bugfix** (`bugfix/*` hacia `develop`): Corrección de un error en desarrollo
- [ ] **Hotfix** (`hotfix/*` hacia `master` y `develop`): Parche crítico en producción
- [ ] **Release** (`release/*` hacia `master` y `develop`): Preparación de nueva versión
- [ ] **Chore / Refactor**: Mantenimiento, dependencias o refactorización sin impacto funcional

## 🎯 Rama de destino
- [ ] `develop` (Flujo estándar para features y mejoras)
- [ ] `master` (Solo para releases y hotfixes)

## 🧪 Pruebas realizadas
<!-- Explica qué pruebas ejecutaste para verificar que no hay regresiones -->
- [ ] Pruebas unitarias / funcionales (`php artisan test` o `./artisan test`)
- [ ] Verificación de servicios en Docker (`docker compose ps`)
- [ ] Compilación de assets (`npm run build`)
- [ ] Pruebas manuales en navegador

## ☑️ Checklist de calidad
- [ ] Mi código sigue los estándares de estilo del proyecto (PSR-12, Laravel standards).
- [ ] No se suben archivos sensibles (`.env`, certificados o contraseñas).
- [ ] Las dependencias nuevas quedaron registradas en `composer.json` / `package.json`.
- [ ] Se incluyeron o actualizaron pruebas automáticas si corresponde.
