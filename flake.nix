{
  description = "Tool for fast atomic deployments to FTP";

  inputs = {
    flake-compat = {
      url = "github:edolstra/flake-compat";
      flake = false;
    };

    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";

    utils.url = "github:numtide/flake-utils";
  };

  outputs =
    { self, flake-compat, nixpkgs, utils }:

    utils.lib.eachDefaultSystem (system:
    let
      pkgs = import nixpkgs {
        inherit system;
      };
    in
    rec {
      devShells = {
        default = pkgs.mkShell {
          nativeBuildInputs = [
            pkgs.python3.pkgs.black
            pkgs.python3.pkgs.poetry
            pkgs.python3.pkgs.mypy
          ];

          buildInputs = self.packages.${system}.fafd.propagatedBuildInputs;
        };
      };

      packages = {
        fafd = pkgs.python3.pkgs.buildPythonApplication {
          pname = "fafd";
          version = "0.1.0";

          format = "pyproject";

          src = ./.;

          nativeBuildInputs = with pkgs.python3.pkgs; [
            poetry-core
          ];

          propagatedBuildInputs = with pkgs.python3.pkgs; [
            requests
            toml
          ];

          passthru.execPath = "/bin/fafd";
        };

        default = self.packages.${system}.fafd;
      };

      apps = {
        default = utils.lib.mkApp {
          drv = self.packages.${system}.fafd;
        };
      };
    });
}
