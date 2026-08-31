# Model integrity

Primary M quant SHA256 verified on VPS before inference:

`635788bdc1b0ba1335e47cca0159e531811c722ffad4e3c7b363c2b55ecc26c8` → MODEL_SHA_OK=YES

S quant SHA256 verified:

`8916be129e5bb4ea16001f73f67888517e41fad86cb14e48b0fe34b333c063d0` → MODEL_SHA_OK=YES

Runtime: llama.cpp **b10726** (required for `qwen35` architecture; b6770 rejected unknown arch).

Bind: `127.0.0.1:3921` only. `AI_LISTENS_PUBLICLY=NO`.
