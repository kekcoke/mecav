variable "laravel_image"  { type = string }
variable "python_image"   { type = string }
variable "namespace"      { type = string; default = "mecav" }
variable "db_secret_name" { type = string; default = "postgres-credentials" }
variable "grpc_host"      { type = string; default = "python-service:50051" }

resource "kubernetes_namespace" "app" {
  metadata { name = var.namespace }
}

resource "helm_release" "laravel" {
  name      = "laravel"
  chart     = "${path.module}/charts/laravel"
  namespace = var.namespace
  atomic    = true
  timeout   = 300

  set { name = "image.repository"; value = split(":", var.laravel_image)[0] }
  set { name = "image.tag";        value = split(":", var.laravel_image)[1] }
  set { name = "grpc.host";        value = var.grpc_host }
  set { name = "db.secretName";    value = var.db_secret_name }
}

resource "helm_release" "python" {
  name      = "python-service"
  chart     = "${path.module}/charts/python"
  namespace = var.namespace
  atomic    = true
  timeout   = 300

  set { name = "image.repository"; value = split(":", var.python_image)[0] }
  set { name = "image.tag";        value = split(":", var.python_image)[1] }
  set { name = "db.secretName";    value = var.db_secret_name }
}
