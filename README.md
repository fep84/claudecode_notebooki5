# claudecode_notebooki5

## Chave SSH pública do notebook i5

Arquivo: `keys/notebook-i5.pub` (ed25519).

Para autorizar o notebook em um servidor:

```sh
cat keys/notebook-i5.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Apenas a chave pública fica neste repositório. A privada nunca deve ser commitada.
