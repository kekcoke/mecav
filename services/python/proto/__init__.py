# proto package — populated by protoc at build time
import pathlib, sys

# Ensure this directory is importable as `proto.multimodal_pb2`
_pkg_dir = pathlib.Path(__file__).resolve().parent
if str(_pkg_dir) not in sys.path:
    sys.path.insert(0, str(_pkg_dir))
