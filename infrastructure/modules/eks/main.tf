terraform {
  required_providers {
    aws        = { source = "hashicorp/aws",        version = "~> 5.0" }
    kubernetes = { source = "hashicorp/kubernetes",  version = "~> 2.29" }
  }
}

variable "cluster_name"    { type = string }
variable "region"          { type = string; default = "us-east-1" }
variable "node_min"        { type = number; default = 2 }
variable "node_max"        { type = number; default = 10 }
variable "node_desired"    { type = number; default = 3 }
variable "node_instance"   { type = string; default = "t3.xlarge" }
variable "vpc_id"          { type = string }
variable "subnet_ids"      { type = list(string) }

module "eks" {
  source          = "terraform-aws-modules/eks/aws"
  version         = "20.8.5"
  cluster_name    = var.cluster_name
  cluster_version = "1.30"
  vpc_id          = var.vpc_id
  subnet_ids      = var.subnet_ids

  cluster_addons = {
    coredns              = { most_recent = true }
    kube-proxy           = { most_recent = true }
    vpc-cni              = { most_recent = true }
    aws-ebs-csi-driver   = { most_recent = true }
  }

  eks_managed_node_groups = {
    # General application workloads
    app = {
      min_size       = var.node_min
      max_size       = var.node_max
      desired_size   = var.node_desired
      instance_types = [var.node_instance]
      capacity_type  = "ON_DEMAND"
      labels         = { workload = "app" }
    }

    # Dedicated GPU Spot group for AI agent sidecars
    agents = {
      min_size       = 0
      max_size       = 4
      desired_size   = 1
      instance_types = ["g4dn.xlarge"]
      capacity_type  = "SPOT"
      labels         = { workload = "agents" }
      taints         = [{ key = "workload", value = "agents", effect = "NO_SCHEDULE" }]
    }
  }

  tags = { Environment = terraform.workspace, ManagedBy = "terraform" }
}

output "cluster_endpoint"   { value = module.eks.cluster_endpoint }
output "cluster_ca_data"    { value = module.eks.cluster_certificate_authority_data }
output "cluster_name"       { value = module.eks.cluster_name }
