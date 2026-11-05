# Monitor de Servidor con Laravel

Este proyecto es una aplicación desarrollada en **Laravel** para monitorear las métricas de hardware y del sistema operativo host (Windows) en tiempo real, exponiéndolas a través de una vista web, una API REST y un endpoint de GraphQL.

---

## 🛠️ Entorno de Desarrollo
* **Sistema Operativo:** Windows 10 / 11
* **PHP:** 8.3+ (XAMPP / Herd / Laragon)
* **Framework:** Laravel 13
* **Motor de Scripting:** PowerShell (`Get-CimInstance` / WMI)
* **GraphQL:** Nuwave Lighthouse + GraphQL Playground

---

## 🚀 Instalación y Puesta en Marcha

1. **Clonar e instalar dependencias:**
   ```bash
   git clone <URL_DE_TU_REPOSITORIO>
   cd monitor-servidor
   composer install
   ```

2. **Configurar variables de entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Iniciar el servidor de desarrollo:**
   ```bash
   php artisan serve
   ```

4. **Rutas disponibles:**
   * **Vista Web:** `http://127.0.0.1:8000/servidor`
   * **API REST:** `http://127.0.0.1:8000/api/metrics`
   * **GraphQL Playground:** `http://127.0.0.1:8000/graphql-playground`

---

## 📝 Respuestas al Cuestionario Evaluativo

### 1. ¿Por qué la temperatura suele aparecer como "No disponible" en Windows?
En Windows, la lectura de la temperatura de la CPU a través de la clase WMI `MSAcpi_ThermalZoneTemperature` (en el espacio de nombres `root/wmi`) requiere **permisos elevados de Administrador** para interactuar con los controladores ACPI de bajo nivel del sistema operativo.

Si el servidor web (PHP / Apache / Artisan Serve) se ejecuta con un usuario estándar sin privilegios administrativos, la consulta a WMI falla en el bloque `try/catch` de PowerShell y devuelve `null`, lo que la aplicación interpreta y muestra como *"No disponible"*. Además, en muchas computadoras modernas o placas madre de escritorio, los fabricantes no exponen sus sensores de temperatura a través del espacio de nombres estándar ACPI de WMI, requiriendo software o controladores propietarios.

---

### 2. ¿Por qué el script de PowerShell nunca debe incluir datos que vengan del usuario?
Permitir que datos provenientes del usuario (parámetros `GET`, `POST`, encabezados HTTP, etc.) se concatenen en la cadena ejecutada por `shell_exec()` introduce una vulnerabilidad crítica conocida como **Inyección de Comandos (Command Injection)**.

Un atacante podría inyectar metacaracteres de consola de Windows (como `;`, `&`, `|` o comillas) para ejecutar comandos arbitrarios del sistema operativo con los mismos privilegios del proceso de PHP. Esto les permitiría leer archivos sensibles, eliminar carpetas del sistema, descargar malware o tomar el control total del servidor. En nuestro servicio `ServerMetrics`, el script ejecutado por PowerShell es estrictamente estático e interno.

---

### 3. ¿Qué riesgo tiene dejar `/api/metrics` sin autenticación en un servidor real?
Dejar este endpoint expuesto públicamente genera un riesgo severo de **Fuga de Información de Infraestructura (Information Disclosure)**:

* **Reconocimiento para ataques:** Muestra las versiones exactas del sistema operativo y de PHP, permitiendo a un atacante buscar vulnerabilidades conocidas (CVEs) específicas para esa versión.
* **Vulnerabilidad a DoS:** Expone el porcentaje exacto de espacio en disco, memoria disponible y uso de CPU, permitiendo a un atacante monitorear cuándo el servidor está bajo alta carga para coordinar un ataque de denegación de servicio (DoS) más efectivo.

En producción, esta ruta debería estar protegida mediante middlewares de autenticación como `auth:sanctum`.

---

### 4. Con polling cada 5 segundos, ¿cuántos pedidos por minuto recibe el servidor si hay 50 personas mirando?
* **Peticiones por usuario:** 60 segundos / 5 segundos = **12 peticiones por minuto por usuario**.
* **Para 50 usuarios simultáneos:** $50 \times 12 = \mathbf{600\text{ peticiones por minuto}}$.

Además de la carga de tráfico web, cada una de estas 600 peticiones fuerza al servidor a instanciar un proceso secundario de PowerShell (`powershell.exe`) para leer WMI, lo que generaría un consumo masivo e innecesario de recursos de CPU y memoria en el servidor host.

---

### 5. Si una app solo necesita la temperatura, ¿qué datos recibe de más al llamar a `/api/metrics`?
Al consultar el endpoint de la API REST (`/api/metrics`), el cliente sufre de **Over-fetching** (recibir más información de la requerida). La API devuelve todo el payload JSON:

* `hostname` (Nombre del equipo)
* `os` (Sistema operativo y versión)
* `php` (Versión de PHP)
* `disk` (`total_gb`, `free_gb`, `used_pct`)
* `memory` (`total_mb`, `available_mb`, `used_pct`)
* `cpu_pct` (Uso de CPU)
* `uptime_s` (Segundos encendido)
* `at` (Marca de tiempo ISO)

Todos estos campos son datos sobrantes no requeridos. Este problema es exactamente el que resuelve **GraphQL**, permitiendo que el cliente consulte únicamente el campo necesario (`cpu_temp_c`) mediante una consulta filtrada.



Preguntas
-----------------------------------------------------------------
1-¿Por qué la temperatura suele aparecer como “No disponibleˮ en Windows? Prueben abrir la terminal
como administrador y comparen.

En mi equipo (DESKTOP-8LEPEML), la temperatura aparece como "No disponible" porque la lectura utiliza la clase MSAcpi_ThermalZoneTemperature dentro del espacio de nombres WMI root/wmi. Esta consulta requiere ejecutarse obligatoriamente con permisos elevados de Administrador en Windows y depende de que la placa madre exponga los sensores ACPI a WMI. Al ejecutar la app sin permisos de administrador (o en placas que no reportan dicho valor), la llamada a PowerShell falla silenciosamente dentro del bloque try-catch y retorna null.


-----------------------------------------------------------------
2-¿Por qué el script de PowerShell nunca debe incluir datos que vengan del usuario?

El script de PowerShell se ejecuta directamente en el sistema operativo mediante la función shell_exec(). Si se incluyeran variables enviadas por el usuario (como parámetros de la URL o entradas de un formulario), la aplicación quedaría expuesta a una vulnerabilidad crítica de Inyección de Comandos (Command Injection). Un atacante podría concatenar sus propios comandos de PowerShell para ejecutar código no autorizado y tomar el control total del servidor.


----------------------------------------------------------------
3-¿Qué riesgo tiene dejar  /api/metrics sin autenticación en un servidor real?

-Exposición de información sensible: Revela públicamente datos internos de la infraestructura, como el nombre de mi máquina (DESKTOP-8LEPEML), el sistema operativo (Windows NT 10.0), la versión exacta de PHP (8.3.31) y el uso real de recursos.   -Riesgo de Denegación de Servicio (DoS): Cada llamada a la API genera y ejecuta un proceso de PowerShell en segundo plano. Peticiones masivas sin autenticar saturarían rápidamente el uso de CPU de la máquina, dejando el servidor inoperativo.   


-----------------------------------------------------------------
4-Con polling cada 5 segundos, ¿cuántos pedidos por minuto recibe el servidor si hay 50 personas
mirando?

-Pedidos por usuario: Como el navegador consulta al servidor cada 5 segundos, un solo usuario realiza 12 pedidos por minuto (60 segundos divididos 5 segundos).   
-Total para 50 usuarios: Si 50 personas tienen la página abierta al mismo tiempo, el servidor recibe 600 solicitudes por minuto (50 usuarios multiplicados por 12 pedidos).


-----------------------------------------------------------------
5-Si una app solo necesita la temperatura, ¿qué datos recibe de más al llamar a  Criterios de evaluación Criterio /api/metrics  ?

Al realizar una petición al endpoint REST /api/metrics, la aplicación sufre del problema de over-fetching (recibir más información de la que realmente necesita). Debido a que la API REST devuelve un objeto JSON con una estructura fija, el servidor envía todos los datos en cada consulta.

Datos recibidos en exceso:
Si la aplicación solo requiere el valor de la temperatura (cpu_temp_c), está recibiendo innecesariamente los siguientes campos:

hostname: Nombre del equipo ("DESKTOP-8LEPEML").

os: Sistema operativo ("Windows NT 10.0").

php: Versión de PHP ("8.3.31").

disk: Objeto completo con información del disco (total_gb, free_gb, used_pct).

memory: Objeto completo con información de la memoria RAM (total_mb, available_mb, used_pct).

cpu_pct: Porcentaje de uso del procesador.

uptime_s: Tiempo que lleva encendido el sistema en segundos.

at: Marca de tiempo en formato ISO.

Impacto en el sistema:
Desperdicio de ancho de banda: Se envían bytes innecesarios a través de la red en cada petición de polling.

Sobrecarga de procesamiento: El servidor ejecuta comandos de PowerShell y consultas a WMI para calcular el estado del disco, la memoria y la CPU, consumo que resulta en vano porque el cliente termina descartando esa información.

Solución con GraphQL:
GraphQL resuelve este problema permitiendo que la aplicación cliente solicite únicamente el campo que necesita. En lugar de traer todo el objeto, se realiza una consulta específica:

GraphQL
query {
  metrics {
    cpu_temp_c
  }
}
De este modo, el servidor responde exclusivamente con el valor de la temperatura, reduciendo el tráfico de datos y optimizando el rendimiento.
