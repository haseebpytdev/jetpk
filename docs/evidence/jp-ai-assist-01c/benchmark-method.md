# Benchmark method

1. Isolated temporary `llama-server` under `jetpk-production-run`.
2. Concurrency=1, context=1024, threads=2, nice=10.
3. Tear down after each round (no permanent service until quality gate).
4. Resource rounds R3/R4 (M quant) + R5 (S quant).
5. Quality: TravelIntent JSON extraction with `chat_template_kwargs.enable_thinking=false`.
6. Structured no-LLM parser scored locally on 20 EN + 20 Roman Urdu + 15 Urdu + 5 mixed prompts.
7. Application path: user → validated TravelIntent → app-chosen tools (never model-named tools).
