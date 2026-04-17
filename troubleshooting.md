# Troubleshooting Report - April 16-17, 2026
**POC**: AdaL
**Status**: Resolved

## TL;DR
Resolved a critical Protobuf `ImportError` in the Python service, fixed Laravel entry point issues, and cleared Docker port conflicts to restore full system connectivity.

---

## 1. Python Service: Protobuf `ImportError`
### Issue
The Python service failed with `ImportError: cannot import name 'runtime_version' from 'google.protobuf'`. This was caused by a version mismatch between the generated gRPC stubs (built with `protoc` 29.0) and the installed `protobuf` library (5.26.1).

### Resolution Steps
1. **Identify Versions**:
   ```bash
   python3 -m grpc_tools.protoc --version # libprotoc 29.0
   pip show protobuf # 5.26.1
   ```
2. **Upgrade Dependencies**:
   ```bash
   python3 -m pip install "protobuf>=5.29.0"
   ```
3. **Regenerate Stubs**:
   ```bash
   python3 -m grpc_tools.protoc -I./protobuf --python_out=./services/python/proto --grpc_python_out=./services/python/proto ./protobuf/multimodal.proto
   ```
4. **Fix Relative Imports**:
   Modified `services/python/proto/multimodal_pb2_grpc.py` to use relative imports:
   `from . import multimodal_pb2 as multimodal__pb2`

---

## 2. Laravel: 404 & Runtime Errors
### Issue
`localhost:8000` was not loading, and the server reported "Failed to open stream" because the `public/index.php` entry point was missing.

### Resolution Steps
1. **Change Port**: Port 8000 was occupied by a zombie process; shifted local testing to 8001.
2. **Restore Entry Points**:
   ```bash
   mkdir -p services/laravel/public
   # Created index.php and .htaccess in public/
   ```
3. **Fix Deprecations**: Updated `PDO::MYSQL_ATTR_SSL_CA` to `\Pdo\Mysql::ATTR_SSL_CA` in `config/database.php` for PHP 8.5 compatibility.

---

## 3. Docker & Database Connectivity
### Issue
`docker-compose up` failed with `Ports are not available` (5432 and 50051), and Laravel could not connect to PostgreSQL due to credential mismatches and stale volumes.

### Resolution Steps
1. **Free Ports**:
   ```bash
   kill <python_pid> # Free 50051
   brew services stop postgresql@16 # Free 5432
   ```
2. **Reset Environment**:
   ```bash
   docker-compose down -v  # Clear stale volumes/roles
   docker-compose up -d
   ```
3. **Verify Connection**:
   ```bash
   docker exec mecav-laravel-api-1 php artisan migrate --force
   ```

---

## Final Verification
- **Laravel**: `http://127.0.0.1:8001` (Host) / `http://localhost:8000` (Docker)
- **Python gRPC**: Listening on `0.0.0.0:50051`
- **Database**: `diagrams_local` initialized and migrated.
