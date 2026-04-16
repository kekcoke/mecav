variable "db_name"            { type = string;  default = "diagrams" }
variable "db_username"        { type = string }
variable "db_password"        { type = string;  sensitive = true }
variable "instance_class"     { type = string;  default = "db.t3.large" }
variable "allocated_storage"  { type = number;  default = 100 }
variable "subnet_ids"         { type = list(string) }
variable "vpc_id"             { type = string }
variable "allowed_sg_ids"     { type = list(string); default = [] }

resource "aws_db_subnet_group" "this" {
  name       = "${var.db_name}-subnet"
  subnet_ids = var.subnet_ids
}

resource "aws_security_group" "rds" {
  name   = "${var.db_name}-rds-sg"
  vpc_id = var.vpc_id
  ingress {
    from_port       = 5432; to_port = 5432; protocol = "tcp"
    security_groups = var.allowed_sg_ids
  }
  egress { from_port = 0; to_port = 0; protocol = "-1"; cidr_blocks = ["0.0.0.0/0"] }
}

resource "aws_db_parameter_group" "pgvector" {
  name   = "${var.db_name}-pg16-pgvector"
  family = "postgres16"
  parameter {
    name         = "shared_preload_libraries"
    value        = "pg_stat_statements,pgvector"
    apply_method = "pending-reboot"
  }
}

resource "aws_db_instance" "postgres" {
  identifier              = var.db_name
  engine                  = "postgres"
  engine_version          = "16.2"
  instance_class          = var.instance_class
  allocated_storage       = var.allocated_storage
  max_allocated_storage   = var.allocated_storage * 5
  storage_type            = "gp3"
  storage_encrypted       = true
  db_name                 = var.db_name
  username                = var.db_username
  password                = var.db_password
  db_subnet_group_name    = aws_db_subnet_group.this.name
  vpc_security_group_ids  = [aws_security_group.rds.id]
  parameter_group_name    = aws_db_parameter_group.pgvector.name
  backup_retention_period = 14
  deletion_protection     = true
  skip_final_snapshot     = false
  final_snapshot_identifier         = "${var.db_name}-final"
  performance_insights_enabled      = true
  enabled_cloudwatch_logs_exports   = ["postgresql", "upgrade"]
  tags = { ManagedBy = "terraform" }
}

output "db_endpoint" { value = aws_db_instance.postgres.endpoint }
output "db_name"     { value = aws_db_instance.postgres.db_name }
