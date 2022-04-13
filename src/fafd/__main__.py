from dataclasses import dataclass, field
from pathlib import Path
from pydantic import validator
from pydantic.dataclasses import dataclass
from typing import Dict, List
from uuid import uuid4
import argparse
import importlib
import json
import random
import requests
import subprocess
import sys
import tempfile
import toml


@dataclass
class Deployment:
    upload_uri: str
    web_uri: str
    web_root: str = ""
    transfer_files: List[str] = field(default_factory=list)

    @validator("upload_uri", "web_uri")
    def check_uri(cls, v):
        return v.rstrip("/")

    @validator("transfer_files", "web_root", each_item=True)
    def check_paths(cls, v):
        return v.strip("/")


def parse_deployment(obj: Dict) -> Deployment:
    return Deployment(**obj)


def parse_config(file: str) -> Dict[str, Deployment]:
    config: Dict = toml.load(file)

    if "deployments" not in config:
        raise ValueError("Missing deployments table in fafd.toml.")

    return {k: parse_deployment(v) for k, v in config["deployments"].items()}


def deploy(args):
    deployments = parse_config("fafd.toml")

    if "default" not in deployments:
        raise ValueError("Missing deployments.default table in fafd.toml.")

    key = str(uuid4())
    archive = Path(args.file)
    selected_deployment = deployments["default"]

    with tempfile.TemporaryDirectory() as temp_dir:
        activation_script_name = "fafd-activate.php"
        activation_script_path = Path(temp_dir) / activation_script_name
        with open(activation_script_path, "w+") as activation_script_file:
            activation_script_text = importlib.resources.read_text("fafd", "activate.php")
            activation_script_text = activation_script_text.replace("@key@", key)
            activation_script_text = activation_script_text.replace("@archive@", archive.name)
            activation_script_text = activation_script_text.replace("@carryover_files@", json.dumps(selected_deployment.transfer_files))
            activation_script_text = activation_script_text.replace("@web_root@", selected_deployment.web_root)
            activation_script_file.write(activation_script_text)

        subprocess.check_output(["gio", "copy", archive, selected_deployment.upload_uri])
        subprocess.check_output(["gio", "copy", activation_script_path, selected_deployment.upload_uri + "/" + selected_deployment.web_root])

        response = requests.post(selected_deployment.web_uri + "/" + activation_script_name, data={"key": key})

        if not response.ok or "success" not in response.text:
            print(f"Deployment failed, status {response.status_code}: {response.text}", file=sys.stderr)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--file", help="Archive file to upload", required=True)

    args = parser.parse_args()

    deploy(args)


if __name__ == "__main__":
    main()
